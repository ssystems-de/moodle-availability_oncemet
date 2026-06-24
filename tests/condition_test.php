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

use availability_profile\condition as profile_condition;
use core_availability\info_module;
use core_availability\mock_info_module;
use core_availability\mock_info_section;
use core_availability\tree;

/**
 * Unit tests for the Once met availability condition.
 *
 * @package    availability_oncemet
 * @copyright  2026 Mahmoud Chehada, ssystems GmbH <mchehada@ssystems.de>
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_oncemet\condition
 */
final class condition_test extends \advanced_testcase {
    /**
     * Load mock info classes.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();

        global $CFG;
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info.php');
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info_module.php');
        require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info_section.php');
    }

    /**
     * Resets the static unlock cache.
     */
    protected function setUp(): void {
        parent::setUp();

        // The database is rolled back by resetAfterTest(), but static state is not.
        condition::wipe_static_cache();
    }

    /**
     * Creates a oncemet condition with a profile child.
     *
     * @param string $email Expected email value.
     * @param string|null $instanceid Stable Once met instance id.
     * @return condition
     */
    protected function create_profile_condition(string $email, ?string $instanceid = 'testinstance1'): condition {
        $child = profile_condition::get_json(false, 'email', profile_condition::OP_IS_EQUAL_TO, $email);
        $structure = condition::get_json([$child], tree::OP_AND, $instanceid);
        return new condition($structure);
    }

    /**
     * Builds a oncemet structure with a profile child and the given raw instance id.
     *
     * The instance id is set on the structure afterwards, as get_json() only takes strings while
     * these structures deliberately carry what a hand-crafted availability field could carry.
     *
     * @param mixed $instanceid Raw instance id to put into the structure.
     * @return \stdClass Structure object representing the condition.
     */
    protected function create_structure($instanceid): \stdClass {
        $child = profile_condition::get_json(false, 'email', profile_condition::OP_IS_EQUAL_TO, 'foo@bar.de');
        $structure = condition::get_json([$child], tree::OP_AND);
        $structure->instanceid = $instanceid;

        return $structure;
    }

    /**
     * Tests that an unlock record is stored when the child condition is true.
     */
    public function test_unlock_stored_when_child_true(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        $cond = $this->create_profile_condition('foo@bar.de');
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertTrue($DB->record_exists('availability_oncemet', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'availabilityuuid' => $cond->get_instance_id(),
        ]));
    }

    /**
     * Tests that a stored unlock keeps availability true after the child becomes false.
     */
    public function test_stored_unlock_keeps_true_when_child_false(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        $cond = $this->create_profile_condition('foo@bar.de');
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));

        $user->email = 'other@example.com';
        $DB->update_record('user', $user);

        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
    }

    /**
     * Tests that an unlock is stored and keeps applying when a NOT is in effect.
     *
     * Within an inverted restriction tree, an item is available as long as the user does not meet
     * the nested restrictions. Once the user has met them, that has to stay so permanently, just as
     * it does without a NOT.
     */
    public function test_unlock_stored_and_kept_when_not_is_in_effect(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        // The user meets the nested restriction, so the inverted condition is not available.
        $cond = $this->create_profile_condition('foo@bar.de');
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
        $this->assertTrue($DB->record_exists('availability_oncemet', [
            'userid' => $user->id,
            'availabilityuuid' => $cond->get_instance_id(),
        ]));

        // The nested restriction does not apply to the user anymore, but the stored unlock keeps the
        // condition met, so the inverted condition stays unavailable.
        $user->email = 'other@example.com';
        $DB->update_record('user', $user);

        $this->assertFalse($cond->is_available(true, $info, true, $user->id));
    }

    /**
     * Tests that no unlock is stored when the child condition is false and a NOT is in effect.
     */
    public function test_no_unlock_when_child_false_and_not_is_in_effect(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'other@example.com']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        $cond = $this->create_profile_condition('foo@bar.de');
        $this->assertTrue($cond->is_available(true, $info, true, $user->id));
        $this->assertFalse($DB->record_exists('availability_oncemet', ['userid' => $user->id]));
    }

    /**
     * Tests that a restriction without nested restrictions never grants access.
     *
     * Such a restriction cannot be built through the form, which rejects it, but it can reach the
     * database through a restore or through code which writes availability fields on its own.
     */
    public function test_unconfigured_restriction_never_grants_access(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        $cond = new condition(condition::get_json([], tree::OP_AND));

        $this->assertFalse($cond->is_available(false, $info, true, $user->id));

        // Within an inverted tree it must not grant access either, which handing the plain result
        // up to the NOT would do.
        $this->assertFalse($cond->is_available(true, $info, true, $user->id));

        $this->assertFalse($DB->record_exists('availability_oncemet', ['userid' => $user->id]));
    }

    /**
     * Tests that a nested restriction whose plugin is gone is dropped instead of breaking the condition.
     *
     * Core decodes an availability tree leniently, so a restriction whose plugin was disabled or
     * uninstalled is dropped while the rest of the tree keeps working. The nested tree has to be
     * decoded the same way: a strict decode throws out of the constructor, which core answers by
     * treating the item as unavailable without any reason text, and an item without a reason is
     * not displayed at all.
     */
    public function test_unknown_nested_plugin_is_dropped(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        // A restriction of a plugin which is not installed, next to one which is.
        $children = [
            (object) ['type' => 'nosuchplugin'],
            profile_condition::get_json(false, 'email', profile_condition::OP_IS_EQUAL_TO, 'foo@bar.de'),
        ];
        $cond = new condition(condition::get_json($children, tree::OP_AND, 'instance-mixed'));

        // The remaining restriction is evaluated as usual and the user earns the unlock from it.
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertTrue($DB->record_exists('availability_oncemet', ['availabilityuuid' => 'instance-mixed']));

        // A restriction which is left with nothing at all is unconfigured and must not grant access,
        // just as one which never had a nested restriction.
        $orphaned = new condition(condition::get_json([(object) ['type' => 'nosuchplugin']], tree::OP_AND, 'instance-gone'));

        $this->assertFalse($orphaned->is_available(false, $info, true, $user->id));
        $this->assertFalse($DB->record_exists('availability_oncemet', ['availabilityuuid' => 'instance-gone']));
    }

    /**
     * Tests that an unlock keeps applying once the plugin of the nested restriction is gone.
     *
     * This is what the lenient decode is really about: the users who got through the restriction
     * while it still worked may not lose their access because an admin disabled a plugin.
     */
    public function test_unlock_survives_unknown_nested_plugin(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        // The user meets the nested restriction while its plugin is still installed.
        $cond = $this->create_profile_condition('foo@bar.de', 'instance-kept');
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));

        // The plugin of the nested restriction is gone, but the Once met instance is the same one,
        // so the stored unlock still applies to it.
        $broken = new condition(condition::get_json([(object) ['type' => 'nosuchplugin']], tree::OP_AND, 'instance-kept'));

        $this->assertTrue($broken->is_available(false, $info, true, $user->id));
    }

    /**
     * Tests that a restriction without nested restrictions is not described with the form message.
     */
    public function test_unconfigured_restriction_description(): void {
        $this->resetAfterTest();
        [$course, $info] = $this->create_description_fixture();

        $cond = new condition(condition::get_json([], tree::OP_AND));

        foreach ([true, false] as $full) {
            $rendered = \core_availability\info::format_info(
                $cond->get_standalone_description($full, false, $info),
                $course
            );

            $this->assertStringContainsString(
                get_string('error_notconfigured', 'availability_oncemet'),
                $rendered
            );

            // The form asks the teacher to add a restriction, which is of no use to anybody who
            // only reads why an activity is not available.
            $this->assertStringNotContainsString(
                get_string('error_nochildren', 'availability_oncemet'),
                $rendered
            );
        }
    }

    /**
     * Tests that no unlock is stored when the child condition is false.
     */
    public function test_no_unlock_when_child_false(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'other@example.com']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        $cond = $this->create_profile_condition('foo@bar.de');
        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($DB->record_exists('availability_oncemet', ['userid' => $user->id]));
    }

    /**
     * Tests that the unlocks of a user and course are read with one single query.
     */
    public function test_unlocks_are_read_once_per_user_and_course(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $otheruser = $generator->create_user(['email' => 'foo@bar.de']);
        $page1 = $generator->create_module('page', ['course' => $course->id]);
        $page2 = $generator->create_module('page', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course, $user->id);
        $info1 = new mock_info_module($user->id, $modinfo->get_cm($page1->cmid));
        $info2 = new mock_info_module($user->id, $modinfo->get_cm($page2->cmid));

        $cond1 = $this->create_profile_condition('foo@bar.de', 'instance-1');
        $cond2 = $this->create_profile_condition('foo@bar.de', 'instance-2');

        // The first question reads the unlocks of the user and the course. It is not counted here,
        // as the very first query against a table also reads the column definitions of that table.
        $cond1->has_unlock($user->id, $info1);

        // Every further question about the same user and course is served from the cache, no matter
        // which Once met instance and which activity of that course it is about.
        $reads = $DB->perf_get_reads();
        $cond1->has_unlock($user->id, $info1);
        $cond2->has_unlock($user->id, $info1);
        $cond1->has_unlock($user->id, $info2);
        $cond2->has_unlock($user->id, $info2);
        $this->assertEquals(0, $DB->perf_get_reads() - $reads);

        // Another user is another cache entry, which takes exactly one query to fill.
        $reads = $DB->perf_get_reads();
        $cond1->has_unlock($otheruser->id, $info1);
        $cond2->has_unlock($otheruser->id, $info2);
        $this->assertEquals(1, $DB->perf_get_reads() - $reads);
    }

    /**
     * Tests that the unlock cache does not grow with the number of users it is asked about.
     *
     * Availability is not only evaluated for the user a page renders for. Scheduled tasks walk
     * through all enrolled users of a course, and an entry per user would pile up for a whole run.
     */
    public function test_unlocks_cache_is_bounded(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);
        $info = new mock_info_module(0, $cm);

        $first = $generator->create_user();
        $second = $generator->create_user();
        $third = $generator->create_user();

        $cond = $this->create_profile_condition('foo@bar.de');

        // The first question reads the unlocks of the user. It is not counted here, as the very
        // first query against a table also reads the column definitions of that table.
        $cond->has_unlock($first->id, $info);

        // Asking about two more users takes one query each and fills the cache up.
        $reads = $DB->perf_get_reads();
        $cond->has_unlock($second->id, $info);
        $cond->has_unlock($third->id, $info);
        $this->assertEquals(2, $DB->perf_get_reads() - $reads);

        // The user who was asked about last is still in the cache.
        $reads = $DB->perf_get_reads();
        $cond->has_unlock($third->id, $info);
        $this->assertEquals(0, $DB->perf_get_reads() - $reads);

        // The first one has made room for them and is read again, which is what keeps the cache
        // from growing by one entry per user.
        $reads = $DB->perf_get_reads();
        $cond->has_unlock($first->id, $info);
        $this->assertEquals(1, $DB->perf_get_reads() - $reads);
    }

    /**
     * Tests that a removed unlock is not reported anymore within the same request.
     *
     * The unlocks are cached for the duration of a request, which must not outlive their removal:
     * a teacher who removes a Once met restriction is shown the course page right afterwards.
     */
    public function test_removed_unlock_is_not_reported_from_cache(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        // The user meets the nested restriction, which stores and caches the unlock.
        $cond = $this->create_profile_condition('foo@bar.de');
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertTrue($cond->has_unlock($user->id, $info));

        // The teacher removes the Once met restriction from the activity, which is what
        // update_moduleinfo() does once it has written the availability field.
        $DB->set_field('course_modules', 'availability', null, ['id' => $cm->id]);
        rebuild_course_cache($course->id, true);
        \core\event\course_module_updated::create_from_cm(
            get_coursemodule_from_id('', $cm->id),
            \context_module::instance($cm->id)
        )->trigger();

        $this->assertFalse($DB->record_exists('availability_oncemet', ['userid' => $user->id]));
        $this->assertFalse($cond->has_unlock($user->id, $info));
    }

    /**
     * Tests that changing nested restrictions keeps unlock when the instance id is unchanged.
     */
    public function test_changed_nested_config_keeps_unlock_with_same_instance(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        $cond = $this->create_profile_condition('foo@bar.de', 'sameinstance');
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));

        $user->email = 'other@example.com';
        $DB->update_record('user', $user);

        // The nested restriction differs from the one which was originally fulfilled, but as the
        // instance id is unchanged, the stored unlock still applies.
        $newcond = $this->create_profile_condition('other@example.com', 'sameinstance');
        $this->assertEquals($cond->get_instance_id(), $newcond->get_instance_id());
        $this->assertTrue($newcond->is_available(false, $info, true, $user->id));
    }

    /**
     * Tests that a new Once met instance does not inherit an existing unlock.
     */
    public function test_new_instance_does_not_inherit_unlock(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        $cond = $this->create_profile_condition('foo@bar.de', 'instance-a');
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));

        $user->email = 'other@example.com';
        $DB->update_record('user', $user);

        // The second instance uses the same nested restriction, which the user does not meet anymore.
        // It must not inherit the unlock which was stored for the first instance.
        $newcond = $this->create_profile_condition('foo@bar.de', 'instance-b');
        $this->assertFalse($newcond->is_available(false, $info, true, $user->id));
        $this->assertFalse($DB->record_exists('availability_oncemet', ['availabilityuuid' => 'instance-b']));

        // The unlock of the first instance is still in place.
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
    }

    /**
     * Tests that guest users do not create unlock records.
     */
    public function test_guest_does_not_create_unlock(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $guest = guest_user();
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);
        $info = new mock_info_module($guest->id, $cm);

        $cond = $this->create_profile_condition('');
        $this->assertFalse($cond->is_available(false, $info, true, $guest->id));
        $this->assertFalse($DB->record_exists('availability_oncemet', ['userid' => $guest->id]));
    }

    /**
     * Tests that duplicate unlock records are avoided.
     */
    public function test_duplicate_unlock_avoided(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        $cond = $this->create_profile_condition('foo@bar.de');
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertTrue($cond->is_available(false, $info, true, $user->id));

        $this->assertEquals(1, $DB->count_records('availability_oncemet', [
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'availabilityuuid' => $cond->get_instance_id(),
        ]));
    }

    /**
     * Tests that a duplicate which a parallel request has stored is dealt with silently.
     *
     * The cache is read before the parallel request writes its record, so the insert runs into the
     * unique index. That is the outcome we want anyway, and it stays an internal matter of this
     * class. It works within a transaction as well, which the test itself runs in on PostgreSQL.
     */
    public function test_duplicate_unlock_from_parallel_request_is_handled(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        $cond = $this->create_profile_condition('foo@bar.de');

        // Ask once, which fills the cache with the unlocks the user holds right now, namely none.
        $this->assertFalse($cond->has_unlock($user->id, $info));

        // A parallel request stores the unlock. This is what the cache cannot know about, and it is
        // what makes the insert below run into the unique index.
        $DB->insert_record('availability_oncemet', (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => $cm->id,
            'sectionid' => 0,
            'availabilityuuid' => $cond->get_instance_id(),
            'timecreated' => time(),
        ]);

        $cond->store_unlock($user->id, $info);

        // The record of the parallel request is the one and only unlock, and it is reported again.
        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['userid' => $user->id]));
        $this->assertTrue($cond->has_unlock($user->id, $info));
    }

    /**
     * Tests activity restriction context stores cmid.
     */
    public function test_activity_context(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        $cond = $this->create_profile_condition('foo@bar.de');
        $cond->is_available(false, $info, true, $user->id);

        $record = $DB->get_record('availability_oncemet', ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertEquals($cm->id, $record->cmid);
        $this->assertEquals(0, $record->sectionid);
    }

    /**
     * Tests section restriction context stores sectionid.
     */
    public function test_section_context(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['numsections' => 2]);
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $modinfo = get_fast_modinfo($course, $user->id);
        $section = $modinfo->get_section_info(1);
        $info = new mock_info_section($user->id, $section);

        $cond = $this->create_profile_condition('foo@bar.de');
        $cond->is_available(false, $info, true, $user->id);

        $record = $DB->get_record('availability_oncemet', ['userid' => $user->id], '*', MUST_EXIST);
        $this->assertEquals($section->id, $record->sectionid);
        $this->assertEquals(0, $record->cmid);
    }

    /**
     * Tests AND group requires all children before storing unlock.
     */
    public function test_and_group_requires_all_children(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_and_enrol($course, 'student', ['email' => 'foo@bar.de', 'department' => 'IT']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        $children = [
            profile_condition::get_json(false, 'email', profile_condition::OP_IS_EQUAL_TO, 'foo@bar.de'),
            profile_condition::get_json(false, 'department', profile_condition::OP_IS_EQUAL_TO, 'Sales'),
        ];
        $structure = condition::get_json($children, tree::OP_AND);
        $cond = new condition($structure);

        $this->assertFalse($cond->is_available(false, $info, true, $user->id));
        $this->assertFalse($DB->record_exists('availability_oncemet', ['userid' => $user->id]));

        $user->department = 'Sales';
        $DB->update_record('user', $user);

        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertTrue($DB->record_exists('availability_oncemet', ['userid' => $user->id]));
    }

    /**
     * Tests OR group stores unlock when any child is true.
     */
    public function test_or_group_stores_on_any_child(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);
        $info = new mock_info_module($user->id, $cm);

        $children = [
            profile_condition::get_json(false, 'email', profile_condition::OP_IS_EQUAL_TO, 'other@example.com'),
            profile_condition::get_json(false, 'email', profile_condition::OP_IS_EQUAL_TO, 'foo@bar.de'),
        ];
        $structure = condition::get_json($children, tree::OP_OR);
        $cond = new condition($structure);

        $this->assertTrue($cond->is_available(false, $info, true, $user->id));
        $this->assertTrue($DB->record_exists('availability_oncemet', ['userid' => $user->id]));
    }

    /**
     * Tests that the database rejects a duplicate unlock even when it belongs to neither an activity nor a section.
     *
     * The unlock columns use 0 instead of NULL exactly so that the unique index covers these records,
     * as all supported databases treat NULL values in a unique index as distinct from each other.
     */
    public function test_duplicate_unlock_rejected_by_database(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();

        $record = (object) [
            'userid' => $user->id,
            'courseid' => $course->id,
            'cmid' => 0,
            'sectionid' => 0,
            'availabilityuuid' => 'duplicateinstance',
            'timecreated' => time(),
        ];

        $DB->insert_record('availability_oncemet', $record);

        $this->expectException(\dml_write_exception::class);
        $DB->insert_record('availability_oncemet', $record);
    }

    /**
     * Tests that a condition which is built without an instance id gets a generated one.
     */
    public function test_generated_instance_id(): void {
        $structure = condition::get_json([
            profile_condition::get_json(false, 'email', profile_condition::OP_IS_EQUAL_TO, 'foo@bar.de'),
        ], tree::OP_AND);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $structure->instanceid
        );

        $cond = new condition($structure);
        $this->assertEquals($structure->instanceid, $cond->get_instance_id());
        $this->assertEquals($structure->instanceid, $cond->save()->instanceid);
    }

    /**
     * Tests that an instance id which is as long as the database column is still accepted.
     */
    public function test_instance_id_of_maximum_length(): void {
        $instanceid = str_repeat('a', 36);
        $cond = $this->create_profile_condition('foo@bar.de', $instanceid);

        $this->assertEquals($instanceid, $cond->get_instance_id());
        $this->assertEquals($instanceid, $cond->save()->instanceid);
    }

    /**
     * Tests that an instance id which is longer than the database column is rejected.
     *
     * Such an id can only arrive from a hand-crafted availability field. It has to be refused here,
     * as storing an unlock for it would either fail with a database exception on the course page or
     * silently truncate the id on a MySQL which does not run in strict mode.
     */
    public function test_instance_id_longer_than_maximum_length(): void {
        $structure = $this->create_structure(str_repeat('a', 37));

        $this->expectException(\coding_exception::class);
        new condition($structure);
    }

    /**
     * Tests that an empty instance id is rejected.
     */
    public function test_empty_instance_id(): void {
        $structure = $this->create_structure('');

        $this->expectException(\coding_exception::class);
        new condition($structure);
    }

    /**
     * Tests that an instance id which is not a string is rejected.
     */
    public function test_non_string_instance_id(): void {
        $structure = $this->create_structure(12345);

        $this->expectException(\coding_exception::class);
        new condition($structure);
    }

    /**
     * Tests that instance ids are extracted from a raw availability field.
     */
    public function test_get_instance_ids(): void {
        $this->assertEquals([], condition::get_instance_ids(null));
        $this->assertEquals([], condition::get_instance_ids(''));
        $this->assertEquals([], condition::get_instance_ids('no json at all'));

        $structure = json_encode([
            'op' => '&',
            'c' => [
                [
                    'type' => 'oncemet',
                    'instanceid' => 'instance-a',
                    'c' => ['op' => '&', 'c' => []],
                ],
                [
                    'type' => 'oncemet',
                    'instanceid' => 'instance-b',
                    'c' => ['op' => '&', 'c' => []],
                ],
            ],
            'showc' => [true, true],
        ]);
        $this->assertEqualsCanonicalizing(['instance-a', 'instance-b'], condition::get_instance_ids($structure));

        // Restrictions of other availability plugins are ignored.
        $other = json_encode([
            'op' => '&',
            'c' => [['type' => 'profile', 'op' => 'isequalto', 'sf' => 'email', 'v' => 'nobody@example.com']],
            'showc' => [true],
        ]);
        $this->assertEquals([], condition::get_instance_ids($other));
    }

    /**
     * Tests that instance ids are also found in nested groups and in nested Once met restrictions.
     */
    public function test_get_instance_ids_nested(): void {
        $structure = json_encode([
            'op' => '&',
            'c' => [
                [
                    // A nested group of restrictions.
                    'op' => '|',
                    'c' => [
                        [
                            'type' => 'oncemet',
                            'instanceid' => 'instance-outer',
                            'c' => [
                                'op' => '&',
                                'c' => [
                                    // A Once met restriction within a Once met restriction.
                                    [
                                        'type' => 'oncemet',
                                        'instanceid' => 'instance-inner',
                                        'c' => ['op' => '&', 'c' => []],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'showc' => [true],
        ]);

        $this->assertEqualsCanonicalizing(
            ['instance-outer', 'instance-inner'],
            condition::get_instance_ids($structure)
        );
    }

    /**
     * Tests description rendering for multiple nested restrictions.
     */
    public function test_multiple_nested_description(): void {
        $this->resetAfterTest();
        [$course, $info] = $this->create_description_fixture();

        $cond = new condition(condition::get_json($this->get_description_children(), tree::OP_AND));
        $description = $cond->get_standalone_description(true, false, $info);

        $this->assertInstanceOf(\core_availability_multiple_messages::class, $description);
        $rendered = \core_availability\info::format_info($description, $course);
        $this->assertStringNotContainsString('{$a}', $rendered);
        $this->assertStringContainsString('Met at least once', $rendered);
        $this->assertStringContainsString('Email address', $rendered);
        $this->assertStringContainsString('Department', $rendered);

        // The nested restrictions have to be rendered as a list below the Once met label, not next to it.
        $this->assertMatchesRegularExpression('~Met at least once:\s*<ul~', $rendered);

        // That list carries the class which keeps it out of the collapsed availability excerpt.
        $this->assertStringContainsString('availability-oncemet-restrictions', $rendered);

        // The nested restrictions are described within the description of the Once met restriction,
        // so core introduces the whole thing once and no nested tree may introduce itself again.
        $this->assert_root_label_used_once($rendered);
    }

    /**
     * Tests the staff description of an item which is set to be hidden entirely.
     *
     * Core appends the 'hidden otherwise' marker to the description of the only restriction of an
     * item with a plain string concatenation, which the list of nested restrictions has to survive.
     */
    public function test_staff_description_of_hidden_item(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();

        // An activity whose only restriction is a Once met restriction with several nested
        // restrictions, with the eye icon of that restriction set to hide the activity entirely.
        $availability = json_encode((object) [
            'op' => tree::OP_AND,
            'c' => [condition::get_json($this->get_description_children(), tree::OP_AND)],
            'showc' => [false],
        ]);
        $page = $generator->create_module('page', ['course' => $course->id, 'availability' => $availability]);
        $cm = get_fast_modinfo($course)->get_cm($page->cmid);

        $rendered = \core_availability\info::format_info((new info_module($cm))->get_full_information(), $course);

        $this->assertStringContainsString('Met at least once', $rendered);
        $this->assertStringContainsString('Email address', $rendered);
        $this->assertStringContainsString('Department', $rendered);
        $this->assertStringContainsString(get_string('hidden_marker', 'availability'), $rendered);
    }

    /**
     * Tests both staff description variants of an inverted Once met restriction.
     */
    public function test_staff_description_of_inverted_restriction(): void {
        $this->resetAfterTest();
        [$course, $info] = $this->create_description_fixture();

        // A single nested restriction is described with the full string.
        $child = profile_condition::get_json(false, 'email', profile_condition::OP_IS_EQUAL_TO, 'foo@bar.de');
        $single = new condition(condition::get_json([$child], tree::OP_AND));
        $rendered = \core_availability\info::format_info($single->get_standalone_description(true, true, $info), $course);

        $this->assertStringNotContainsString('{$a}', $rendered);
        $this->assertStringContainsString('Not yet met at least once: ', $rendered);
        $this->assertStringContainsString('Email address', $rendered);
        $this->assert_root_label_used_once($rendered);

        // Several nested restrictions are labelled with the prefix string and are listed below it.
        $multiple = new condition(condition::get_json($this->get_description_children(), tree::OP_AND));
        $rendered = \core_availability\info::format_info($multiple->get_standalone_description(true, true, $info), $course);

        $this->assertStringNotContainsString('{$a}', $rendered);
        $this->assertMatchesRegularExpression('~Not yet met at least once:\s*<ul~', $rendered);
        $this->assertStringContainsString('Department', $rendered);
        $this->assert_root_label_used_once($rendered);
    }

    /**
     * Asserts that the label with which core introduces a restriction tree appears exactly once.
     *
     * The description of a Once met restriction embeds a tree of nested restrictions. Both the outer
     * tree and the nested one can carry that label, which used to produce descriptions reading
     * "Not available unless: Not available unless: ...".
     *
     * @param string $rendered Rendered description.
     */
    protected function assert_root_label_used_once(string $rendered): void {
        $label = get_string('list_root_and', 'availability');

        $this->assertEquals(
            1,
            substr_count($rendered, $label),
            'The "' . $label . '" label has to introduce the description exactly once, got: ' . $rendered
        );
    }

    /**
     * Tests that learners are not told about the Once met mechanism.
     */
    public function test_learner_description_hides_oncemet(): void {
        $this->resetAfterTest();
        [$course, $info] = $this->create_description_fixture();

        $cond = new condition(condition::get_json($this->get_description_children(), tree::OP_AND));
        $description = $cond->get_standalone_description(false, false, $info);

        $rendered = \core_availability\info::format_info($description, $course);
        $this->assertStringNotContainsString('Met at least once', $rendered);
        $this->assertStringContainsString('Email address', $rendered);
        $this->assertStringContainsString('Department', $rendered);
    }

    /**
     * Tests that a single nested restriction is described to learners just like a normal restriction.
     */
    public function test_learner_description_of_single_child_matches_plain_restriction(): void {
        $this->resetAfterTest();
        [$course, $info] = $this->create_description_fixture();

        $child = profile_condition::get_json(false, 'email', profile_condition::OP_IS_EQUAL_TO, 'foo@bar.de');
        $cond = new condition(condition::get_json([$child], tree::OP_AND));
        $plain = new profile_condition($child);

        $this->assertEquals(
            \core_availability\info::format_info($plain->get_standalone_description(false, false, $info), $course),
            \core_availability\info::format_info($cond->get_standalone_description(false, false, $info), $course)
        );
    }

    /**
     * Tests that a NOT in effect is handed down to the nested restrictions in the learner description.
     */
    public function test_learner_description_inverts_nested_restrictions(): void {
        $this->resetAfterTest();
        [$course, $info] = $this->create_description_fixture();

        $child = profile_condition::get_json(false, 'email', profile_condition::OP_IS_EQUAL_TO, 'foo@bar.de');
        $cond = new condition(condition::get_json([$child], tree::OP_AND));
        $plain = new profile_condition($child);

        $this->assertEquals(
            \core_availability\info::format_info($plain->get_standalone_description(false, true, $info), $course),
            \core_availability\info::format_info($cond->get_standalone_description(false, true, $info), $course)
        );
    }

    /**
     * Creates the course and availability info object which the description tests share.
     *
     * @return array Course record and availability info object.
     */
    protected function create_description_fixture(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user(['email' => 'foo@bar.de', 'department' => 'IT']);
        $page = $generator->create_module('page', ['course' => $course->id]);
        $cm = get_fast_modinfo($course, $user->id)->get_cm($page->cmid);

        return [$course, new mock_info_module($user->id, $cm)];
    }

    /**
     * Returns two nested restrictions which the description tests share.
     *
     * @return \stdClass[] Nested restriction structures.
     */
    protected function get_description_children(): array {
        return [
            profile_condition::get_json(false, 'email', profile_condition::OP_IS_EQUAL_TO, 'foo@bar.de'),
            profile_condition::get_json(false, 'department', profile_condition::OP_IS_EQUAL_TO, 'IT'),
        ];
    }
}
