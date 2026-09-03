<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace availability_oncemet;

/**
 * Availability OnceMet - Unit tests for the event observers.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_oncemet\observer
 */
final class observer_test extends \advanced_testcase {
    /** @var \stdClass Test course. */
    protected \stdClass $course;

    /** @var \stdClass Test user. */
    protected \stdClass $user;

    /**
     * Creates a course and a user.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        // The database is rolled back by resetAfterTest(), but static state is not.
        condition::wipe_static_cache();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['numsections' => 2]);
        $this->user = $generator->create_and_enrol($this->course, 'student');
    }

    /**
     * Returns the data generator of the plugin, which builds restrictions and unlocks.
     *
     * It is the same generator which the Behat steps of the plugin build on, see
     * {@see \behat_availability_oncemet_generator}.
     *
     * @return \availability_oncemet_generator
     */
    protected function oncemet_generator(): \availability_oncemet_generator {
        return $this->getDataGenerator()->get_plugin_generator('availability_oncemet');
    }

    /**
     * Adds a Once met restriction to an activity.
     *
     * Which restrictions are nested inside does not matter for the observers, as they only look at
     * the instance ids of an availability field, so every restriction of these tests looks alike.
     *
     * @param int $cmid Course module id.
     * @param string $instanceid Once met instance id.
     */
    protected function restrict_module(int $cmid, string $instanceid): void {
        $this->oncemet_generator()->create_activity_restriction([
            'cmid' => $cmid,
            'instanceid' => $instanceid,
            'profile' => 'email = nobody@example.com',
        ]);
    }

    /**
     * Builds the raw availability JSON of one or more Once met restrictions.
     *
     * The data generator of the plugin only ever appends a restriction, which is what a teacher does
     * in the form, so it cannot express the state which an activity is left in after a restriction
     * was taken away again. That is what this is for, and it is therefore only used for the target
     * state of an edit, never to set a test up.
     *
     * @param string[] $instanceids Instance ids of the Once met restrictions.
     * @return string
     */
    protected function build_availability(array $instanceids): string {
        $children = [];
        foreach ($instanceids as $instanceid) {
            $children[] = [
                'type' => 'oncemet',
                'instanceid' => $instanceid,
                'c' => [
                    'op' => '&',
                    'c' => [['type' => 'profile', 'op' => 'isequalto', 'sf' => 'email', 'v' => 'nobody@example.com']],
                ],
            ];
        }

        return json_encode([
            'op' => '&',
            'c' => $children,
            'showc' => array_fill(0, count($children), true),
        ]);
    }

    /**
     * Sets the availability of an activity and triggers the event which Moodle triggers as well.
     *
     * This mirrors what update_moduleinfo() does once it has written the availability field, see the
     * end of course/modlib.php. Going through update_moduleinfo() itself is not possible here, as it
     * expects a complete set of module form data.
     *
     * @param int $cmid Course module id.
     * @param string|null $availability Raw availability JSON, null to remove all restrictions.
     */
    protected function update_module_availability(int $cmid, ?string $availability): void {
        global $DB;

        $DB->set_field('course_modules', 'availability', $availability, ['id' => $cmid]);
        rebuild_course_cache($this->course->id, true);

        $cm = get_coursemodule_from_id('', $cmid);
        \core\event\course_module_updated::create_from_cm($cm, \context_module::instance($cmid))->trigger();
    }

    /**
     * Creates an unlock record.
     *
     * @param array $item Item the unlock belongs to, either a 'cmid' or a 'courseid' and a 'section'.
     * @param string $instanceid Once met instance id.
     * @param int|null $userid User id, defaults to the test user.
     */
    protected function create_unlock(array $item, string $instanceid, ?int $userid = null): void {
        $this->oncemet_generator()->create_unlock($item + [
            'userid' => $userid ?? $this->user->id,
            'instanceid' => $instanceid,
        ]);
    }

    /**
     * Tests that deleting a course removes all its unlock records.
     */
    public function test_course_deleted(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $othercourse = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $this->course->id]);
        $otherpage = $generator->create_module('page', ['course' => $othercourse->id]);

        // An unlock of an activity and one of a course section, as the course deletion has to take
        // both of them along no matter which of the two columns they are tied to.
        $this->create_unlock(['cmid' => $page->cmid], 'instance-cm');
        $this->create_unlock(['courseid' => $this->course->id, 'section' => 1], 'instance-sec');

        // An unlock in another course which must survive.
        $this->create_unlock(['cmid' => $otherpage->cmid], 'instance-other');

        delete_course($this->course, false);

        $this->assertEquals(0, $DB->count_records('availability_oncemet', ['courseid' => $this->course->id]));
        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['courseid' => $othercourse->id]));
    }

    /**
     * Tests that deleting an activity removes its unlock records.
     */
    public function test_course_module_deleted(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $page1 = $generator->create_module('page', ['course' => $this->course->id]);
        $page2 = $generator->create_module('page', ['course' => $this->course->id]);

        $this->create_unlock(['cmid' => $page1->cmid], 'instance-cm1');
        $this->create_unlock(['cmid' => $page2->cmid], 'instance-cm2');

        course_delete_module($page1->cmid);

        $this->assertEquals(0, $DB->count_records('availability_oncemet', ['cmid' => $page1->cmid]));
        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['cmid' => $page2->cmid]));
    }

    /**
     * Tests that deleting a course section removes its unlock records.
     */
    public function test_course_section_deleted(): void {
        global $DB;

        $modinfo = get_fast_modinfo($this->course);
        $section1 = $modinfo->get_section_info(1);
        $section2 = $modinfo->get_section_info(2);

        $this->create_unlock(['courseid' => $this->course->id, 'section' => 1], 'instance-sec1');
        $this->create_unlock(['courseid' => $this->course->id, 'section' => 2], 'instance-sec2');

        course_delete_section($this->course, $section1->section, true);

        $this->assertEquals(0, $DB->count_records('availability_oncemet', ['sectionid' => $section1->id]));
        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['sectionid' => $section2->id]));
    }

    /**
     * Tests that deleting a user removes their unlock records.
     */
    public function test_user_deleted(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $otheruser = $generator->create_and_enrol($this->course, 'student');
        $page = $generator->create_module('page', ['course' => $this->course->id]);

        $this->create_unlock(['cmid' => $page->cmid], 'instance-cm');
        $this->create_unlock(['cmid' => $page->cmid], 'instance-cm', $otheruser->id);

        delete_user($this->user);

        $this->assertEquals(0, $DB->count_records('availability_oncemet', ['userid' => $this->user->id]));
        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['userid' => $otheruser->id]));
    }

    /**
     * Tests that resetting a course which unenrols its users removes its unlock records.
     */
    public function test_course_reset_ended_unenrolling_users(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $othercourse = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $this->course->id]);
        $otherpage = $generator->create_module('page', ['course' => $othercourse->id]);

        // A teacher whose role is not unenrolled, and a student who holds two roles and therefore
        // only loses the student role while keeping the enrolment.
        $teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $twinroled = $generator->create_and_enrol($this->course, 'student');
        $generator->enrol_user($twinroled->id, $this->course->id, 'editingteacher');

        $this->create_unlock(['cmid' => $page->cmid], 'instance-cm');
        $this->create_unlock(['cmid' => $page->cmid], 'instance-cm', $teacher->id);
        $this->create_unlock(['cmid' => $page->cmid], 'instance-cm', $twinroled->id);

        // An unlock in another course which must survive.
        $this->create_unlock(['cmid' => $otherpage->cmid], 'instance-other');

        $resetdata = new \stdClass();
        $resetdata->id = $this->course->id;
        $resetdata->unenrol_users = [$DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST)];
        reset_course_userdata($resetdata);

        // The plain student lost the enrolment and therefore the unlock.
        $this->assertEquals(0, $DB->count_records('availability_oncemet', [
            'userid' => $this->user->id,
            'courseid' => $this->course->id,
        ]));

        // The teacher was never up for unenrolment and keeps the unlock.
        $this->assertEquals(1, $DB->count_records('availability_oncemet', [
            'userid' => $teacher->id,
            'courseid' => $this->course->id,
        ]));

        // The user with two roles only lost the student role. The enrolment and the unlock remain.
        $this->assertTrue(is_enrolled(\context_course::instance($this->course->id), $twinroled->id));
        $this->assertEquals(1, $DB->count_records('availability_oncemet', [
            'userid' => $twinroled->id,
            'courseid' => $this->course->id,
        ]));

        // The unlock which the student holds in another course is not affected at all.
        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['courseid' => $othercourse->id]));
    }

    /**
     * Tests that resetting a course which keeps its users enrolled keeps the unlock records.
     *
     * What a user met once stays met, so wiping completion data or grades does not revoke an unlock.
     */
    public function test_course_reset_ended_keeping_users(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $page = $generator->create_module('page', ['course' => $this->course->id]);

        $this->create_unlock(['cmid' => $page->cmid], 'instance-cm');

        $resetdata = new \stdClass();
        $resetdata->id = $this->course->id;
        $resetdata->reset_completion = 1;
        reset_course_userdata($resetdata);

        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['courseid' => $this->course->id]));
    }

    /**
     * Tests that removing one of two Once met restrictions from an activity only removes its unlock records.
     */
    public function test_course_module_updated_removes_obsolete_unlocks_only(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $page = $generator->create_module('page', ['course' => $this->course->id]);

        // Adding the restrictions one after the other is what a teacher does in the form, so the
        // activity ends up with the very availability field which the form would have written.
        $this->restrict_module($page->cmid, 'instance-keep');
        $this->restrict_module($page->cmid, 'instance-drop');

        $this->create_unlock(['cmid' => $page->cmid], 'instance-keep');
        $this->create_unlock(['cmid' => $page->cmid], 'instance-drop');

        // Remove the second restriction from the activity.
        $this->update_module_availability($page->cmid, $this->build_availability(['instance-keep']));

        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['availabilityuuid' => 'instance-keep']));
        $this->assertEquals(0, $DB->count_records('availability_oncemet', ['availabilityuuid' => 'instance-drop']));
    }

    /**
     * Tests that removing all Once met restrictions from an activity removes all its unlock records.
     */
    public function test_course_module_updated_removes_all_unlocks(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $page = $generator->create_module('page', ['course' => $this->course->id]);
        $otherpage = $generator->create_module('page', ['course' => $this->course->id]);

        $this->restrict_module($page->cmid, 'instance-drop');

        $this->create_unlock(['cmid' => $page->cmid], 'instance-drop');
        $this->create_unlock(['cmid' => $otherpage->cmid], 'instance-other');

        $this->update_module_availability($page->cmid, null);

        $this->assertEquals(0, $DB->count_records('availability_oncemet', ['cmid' => $page->cmid]));

        // The unlock of the untouched activity is not affected.
        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['cmid' => $otherpage->cmid]));
    }

    /**
     * Tests that removing a Once met restriction from a course section removes its unlock records.
     */
    public function test_course_section_updated(): void {
        global $DB;

        $modinfo = get_fast_modinfo($this->course);
        $section1 = $modinfo->get_section_info(1);
        $section2 = $modinfo->get_section_info(2);

        $this->oncemet_generator()->create_section_restriction([
            'courseid' => $this->course->id,
            'section' => 1,
            'instanceid' => 'instance-sec1',
            'profile' => 'email = nobody@example.com',
        ]);

        $this->create_unlock(['courseid' => $this->course->id, 'section' => 1], 'instance-sec1');
        $this->create_unlock(['courseid' => $this->course->id, 'section' => 2], 'instance-sec2');

        course_update_section($this->course, $section1, ['availability' => null]);

        $this->assertEquals(0, $DB->count_records('availability_oncemet', ['sectionid' => $section1->id]));

        // The unlock of the untouched section is not affected.
        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['sectionid' => $section2->id]));
    }
}
