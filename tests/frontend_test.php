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

/**
 * Availability OnceMet - Test for the plugin form frontend.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_oncemet;

/**
 * Unit tests for the frontend.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @coversDefaultClass \availability_oncemet\frontend
 */
final class frontend_test extends \advanced_testcase {
    /**
     * Reset the database for every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * allow_add is true for an editing teacher, who holds the capability by default.
     *
     * @covers ::allow_add
     */
    public function test_allow_add_true_for_teacher(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);

        $this->assertTrue($this->allow_add($course));
    }

    /**
     * allow_add is true for a manager, who holds the capability by default.
     *
     * @covers ::allow_add
     */
    public function test_allow_add_true_for_manager(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $manager = $generator->create_and_enrol($course, 'manager');

        $this->setUser($manager);

        $this->assertTrue($this->allow_add($course));
    }

    /**
     * allow_add is false when the user lacks the addinstance capability.
     *
     * @covers ::allow_add
     */
    public function test_allow_add_false_without_capability(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student');

        $this->setUser($student);

        $this->assertFalse($this->allow_add($course));
    }

    /**
     * allow_add is false when the capability is withdrawn in the course.
     *
     * @covers ::allow_add
     */
    public function test_allow_add_false_when_capability_prohibited_in_course(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $this->prohibit_addinstance('editingteacher', \context_course::instance($course->id));

        $this->setUser($teacher);

        $this->assertFalse($this->allow_add($course));
    }

    /**
     * The capability is checked in the module context when an activity is edited.
     *
     * @covers ::allow_add
     */
    public function test_allow_add_uses_module_context(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        // Withdraw the capability for this activity only. The course itself stays untouched, so a
        // check which ignores the module context would still report true.
        $this->prohibit_addinstance('editingteacher', \context_module::instance($page->cmid));

        $this->setUser($teacher);

        $this->assertFalse($this->allow_add($course, $cm));
        $this->assertTrue($this->allow_add($course));
    }

    /**
     * An activity without any Once met restriction hands no identifier to the form.
     *
     * @covers ::get_javascript_init_params
     */
    public function test_init_params_without_restriction(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        $this->assertSame([], $this->init_params($course, $cm));
    }

    /**
     * A Once met restriction which nobody has unlocked yet is not reported to the form.
     *
     * @covers ::get_javascript_init_params
     */
    public function test_init_params_restriction_without_unlocks(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $this->restrict_module($page->cmid, 'UNLOCKED1');
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        $this->assertSame([], $this->init_params($course, $cm));
    }

    /**
     * A Once met restriction which somebody has unlocked is reported to the form.
     *
     * @covers ::get_javascript_init_params
     */
    public function test_init_params_restriction_with_unlocks(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $this->restrict_module($page->cmid, 'UNLOCKED1');
        $this->store_unlock($student->id, ['cmid' => $page->cmid], 'UNLOCKED1');
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        $this->assertSame(['UNLOCKED1'], $this->init_params($course, $cm));
    }

    /**
     * The unlock which the editing user holds themselves does not count.
     *
     * Teachers gather unlock records of their own just by looking at a course page, and those
     * records are never used for anything, so warning about them would be warning about nothing.
     *
     * @covers ::get_javascript_init_params
     */
    public function test_init_params_ignores_unlock_of_editing_user(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->restrict_module($page->cmid, 'UNLOCKED1');
        $this->store_unlock($teacher->id, ['cmid' => $page->cmid], 'UNLOCKED1');
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        $this->assertSame([], $this->init_params($course, $cm, null, $teacher));
    }

    /**
     * An unlock of somebody else counts even when the editing user holds one as well.
     *
     * @covers ::get_javascript_init_params
     */
    public function test_init_params_counts_unlock_of_other_user(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $student = $generator->create_and_enrol($course, 'student');
        $this->restrict_module($page->cmid, 'UNLOCKED1');
        $this->store_unlock($teacher->id, ['cmid' => $page->cmid], 'UNLOCKED1');
        $this->store_unlock($student->id, ['cmid' => $page->cmid], 'UNLOCKED1');
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        $this->assertSame(['UNLOCKED1'], $this->init_params($course, $cm, null, $teacher));
    }

    /**
     * Only the restrictions of the edited activity are reported, not those of its neighbours.
     *
     * @covers ::get_javascript_init_params
     */
    public function test_init_params_ignores_other_activities(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $other = $generator->create_module('page', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');

        // Both activities carry a restriction, but only the other one has been unlocked.
        $this->restrict_module($page->cmid, 'UNLOCKED1');
        $this->restrict_module($other->cmid, 'UNLOCKED2');
        $this->store_unlock($student->id, ['cmid' => $other->cmid], 'UNLOCKED2');

        $modinfo = get_fast_modinfo($course);

        $this->assertSame([], $this->init_params($course, $modinfo->get_cm($page->cmid)));
        $this->assertSame(['UNLOCKED2'], $this->init_params($course, $modinfo->get_cm($other->cmid)));
    }

    /**
     * An unlock which does not belong to any of the stored restrictions anymore is not reported.
     *
     * Such a record only exists between the removal of a restriction and the observer which cleans
     * up after it, but reporting it would warn about a restriction which the form does not show.
     *
     * @covers ::get_javascript_init_params
     */
    public function test_init_params_ignores_orphaned_unlocks(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');
        $this->restrict_module($page->cmid, 'UNLOCKED1');
        $this->store_unlock($student->id, ['cmid' => $page->cmid], 'GONE1');
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        $this->assertSame([], $this->init_params($course, $cm));
    }

    /**
     * A course section reports the unlocked restrictions which are stored on the section.
     *
     * @covers ::get_javascript_init_params
     */
    public function test_init_params_for_section(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2]);
        $student = $generator->create_and_enrol($course, 'student');

        $this->oncemet_generator()->create_section_restriction([
            'courseid' => $course->id,
            'section' => 1,
            'instanceid' => 'UNLOCKED1',
            'profile' => 'email = nobody@example.com',
        ]);
        $this->store_unlock($student->id, ['courseid' => $course->id, 'section' => 1], 'UNLOCKED1');

        $section = get_fast_modinfo($course)->get_section_info(1);

        $this->assertSame(['UNLOCKED1'], $this->init_params($course, null, $section));
    }

    /**
     * An activity which is still being created cannot hold an unlock, so nothing is reported.
     *
     * @covers ::get_javascript_init_params
     */
    public function test_init_params_without_item(): void {
        $course = $this->getDataGenerator()->create_course();

        $this->assertSame([], $this->init_params($course));
    }

    /**
     * Every stored restriction gets a report URL, including the ones which nobody has unlocked.
     *
     * @covers ::get_report_urls
     */
    public function test_report_params_covers_every_stored_restriction(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $student = $generator->create_and_enrol($course, 'student');

        $this->restrict_module($page->cmid, 'UNLOCKED1');
        $this->restrict_module($page->cmid, 'UNLOCKED2');
        $this->store_unlock($student->id, ['cmid' => $page->cmid], 'UNLOCKED1');

        $cm = get_fast_modinfo($course)->get_cm($page->cmid);
        $urls = $this->report_params($course, $cm);

        // The restriction which nobody has unlocked is offered just like the other one: that its
        // report is empty is an answer which the report itself gives.
        $this->assertSame(['UNLOCKED1', 'UNLOCKED2'], array_keys($urls));
        $this->assertStringContainsString('cmid=' . $page->cmid, $urls['UNLOCKED1']);
        $this->assertStringContainsString('instanceid=UNLOCKED1', $urls['UNLOCKED1']);
        $this->assertStringContainsString('instanceid=UNLOCKED2', $urls['UNLOCKED2']);
    }

    /**
     * The report URL carries the page which the report has to return to.
     *
     * @covers ::get_report_urls
     */
    public function test_report_params_carry_the_return_url(): void {
        global $PAGE;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);

        $this->restrict_module($page->cmid, 'UNLOCKED1');

        // The availability form is rendered on the settings page of the activity, so that is the
        // page which the report has to lead back to.
        $PAGE->set_url('/course/modedit.php', ['update' => $page->cmid]);

        $cm = get_fast_modinfo($course)->get_cm($page->cmid);
        $urls = $this->report_params($course, $cm);

        $this->assertStringContainsString('returnurl=', $urls['UNLOCKED1']);
        $this->assertStringContainsString(rawurlencode('/course/modedit.php'), $urls['UNLOCKED1']);
    }

    /**
     * A course section offers the reports of the restrictions which are stored on the section.
     *
     * @covers ::get_report_urls
     */
    public function test_report_params_for_section(): void {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2]);

        $this->oncemet_generator()->create_section_restriction([
            'courseid' => $course->id,
            'section' => 1,
            'instanceid' => 'UNLOCKED1',
            'profile' => 'city = Nowhere',
        ]);

        $section = get_fast_modinfo($course)->get_section_info(1);
        $urls = $this->report_params($course, null, $section);

        $this->assertStringContainsString('sectionid=' . $section->id, $urls['UNLOCKED1']);
    }

    /**
     * Users who may not see the unlocks are not offered the report either.
     *
     * @covers ::get_report_urls
     */
    public function test_report_params_without_capability(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $teacher = $generator->create_and_enrol($course, 'editingteacher');

        $this->restrict_module($page->cmid, 'UNLOCKED1');

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        role_change_permission(
            $roleid,
            \context_module::instance($page->cmid),
            'availability/oncemet:viewunlocks',
            CAP_PROHIBIT
        );

        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        $this->assertSame([], $this->report_params($course, $cm, null, $teacher));
    }

    /**
     * An activity which is still being created has no restriction to report on.
     *
     * @covers ::get_report_urls
     */
    public function test_report_params_without_item(): void {
        $course = $this->getDataGenerator()->create_course();

        $this->assertSame([], $this->report_params($course));
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
     * Which restrictions are nested inside does not matter here, as the form is told about unlocks
     * and not about what has to be met for them, so every restriction of these tests looks alike.
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
     * Stores an unlock record, as if the user had fulfilled the nested restrictions at some point.
     *
     * @param int $userid User id.
     * @param array $item Item the unlock belongs to, either a 'cmid' or a 'courseid' and a 'section'.
     * @param string $instanceid Once met instance id.
     */
    protected function store_unlock(int $userid, array $item, string $instanceid): void {
        $this->oncemet_generator()->create_unlock($item + [
            'userid' => $userid,
            'instanceid' => $instanceid,
        ]);
    }

    /**
     * Call the protected get_javascript_init_params() of the frontend and unwrap the identifiers of
     * the restrictions which hold unlocks.
     *
     * Which user is asking matters, as their own unlocks are left out of the answer, so the editing
     * user is always set rather than left to whatever the test environment happens to hold.
     *
     * @param \stdClass $course Course.
     * @param \cm_info|null $cm Module.
     * @param \section_info|null $section Section.
     * @param \stdClass|null $editinguser User who is editing, an editing teacher of the course by default.
     * @return string[] Identifiers of the restrictions which hold unlocks.
     */
    protected function init_params(
        \stdClass $course,
        ?\cm_info $cm = null,
        ?\section_info $section = null,
        ?\stdClass $editinguser = null
    ): array {
        return $this->all_init_params($course, $cm, $section, $editinguser)[0];
    }

    /**
     * Call the protected get_javascript_init_params() of the frontend and unwrap the report URLs.
     *
     * @param \stdClass $course Course.
     * @param \cm_info|null $cm Module.
     * @param \section_info|null $section Section.
     * @param \stdClass|null $editinguser User who is editing, an editing teacher of the course by default.
     * @return string[] Report URL of each stored restriction, keyed by its instance id.
     */
    protected function report_params(
        \stdClass $course,
        ?\cm_info $cm = null,
        ?\section_info $section = null,
        ?\stdClass $editinguser = null
    ): array {
        return $this->all_init_params($course, $cm, $section, $editinguser)[1];
    }

    /**
     * Call the protected get_javascript_init_params() of the frontend.
     *
     * @param \stdClass $course Course.
     * @param \cm_info|null $cm Module.
     * @param \section_info|null $section Section.
     * @param \stdClass|null $editinguser User who is editing, an editing teacher of the course by default.
     * @return array The parameters which the form JavaScript is handed.
     */
    protected function all_init_params(
        \stdClass $course,
        ?\cm_info $cm = null,
        ?\section_info $section = null,
        ?\stdClass $editinguser = null
    ): array {
        $this->setUser($editinguser ?? $this->getDataGenerator()->create_and_enrol($course, 'editingteacher'));

        $frontend = new frontend();
        $method = new \ReflectionMethod(frontend::class, 'get_javascript_init_params');
        $method->setAccessible(true);
        $params = $method->invoke($frontend, $course, $cm, $section);

        $this->assertCount(2, $params);

        return $params;
    }

    /**
     * Withdraw the addinstance capability from a role in a given context.
     *
     * @param string $roleshortname Role shortname.
     * @param \context $context Context to withdraw the capability in.
     */
    protected function prohibit_addinstance(string $roleshortname, \context $context): void {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => $roleshortname], MUST_EXIST);
        role_change_permission($roleid, $context, 'availability/oncemet:addinstance', CAP_PROHIBIT);
    }

    /**
     * Call the protected allow_add() of the frontend.
     *
     * @param \stdClass $course Course.
     * @param \cm_info|null $cm Module.
     * @param \section_info|null $section Section.
     * @return bool
     */
    protected function allow_add(\stdClass $course, ?\cm_info $cm = null, ?\section_info $section = null): bool {
        $frontend = new frontend();
        $method = new \ReflectionMethod(frontend::class, 'allow_add');
        $method->setAccessible(true);
        return (bool) $method->invoke($frontend, $course, $cm, $section);
    }
}
