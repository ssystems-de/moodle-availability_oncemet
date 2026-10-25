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

namespace availability_oncemet\event;

use availability_oncemet\condition;
use availability_oncemet\local\unlocks;
use availability_profile\condition as profile_condition;
use core_availability\info_module;
use core_availability\info_section;
use core_availability\tree;

/**
 * Availability OnceMet - Unit tests for the events.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_oncemet\event\unlock_base
 * @covers     \availability_oncemet\event\unlock_gained
 * @covers     \availability_oncemet\event\unlock_reset
 */
final class events_test extends \advanced_testcase {
    /** @var string Once met instance id of the tests. */
    protected const INSTANCEID = '1b4e28ba-2fa1-41d2-883f-0016d3cca427';

    /** @var \stdClass Test course. */
    protected \stdClass $course;

    /** @var \stdClass Test user, who meets the nested restrictions of the tests. */
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
        $this->user = $generator->create_and_enrol($this->course, 'student', ['email' => 'foo@bar.de']);
    }

    /**
     * Builds a Once met restriction which the test user meets.
     *
     * @return condition
     */
    protected function create_condition(): condition {
        $child = profile_condition::get_json(false, 'email', profile_condition::OP_IS_EQUAL_TO, 'foo@bar.de');
        return new condition(condition::get_json([$child], tree::OP_AND, self::INSTANCEID));
    }

    /**
     * Creates an unlock record.
     *
     * @param array $item Item the unlock belongs to, either a 'cmid' or a 'courseid' and a 'section'.
     * @param int $userid User id.
     */
    protected function create_unlock(array $item, int $userid): void {
        $this->getDataGenerator()->get_plugin_generator('availability_oncemet')->create_unlock($item + [
            'userid' => $userid,
            'instanceid' => self::INSTANCEID,
        ]);
    }

    /**
     * Tests that gaining an unlock on an activity is logged once, in the module context.
     */
    public function test_unlock_gained_on_activity(): void {
        global $DB;

        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $cm = get_fast_modinfo($this->course, $this->user->id)->get_cm($page->cmid);
        $info = new info_module($cm);
        $cond = $this->create_condition();

        $sink = $this->redirectEvents();
        $cond->is_available(false, $info, true, $this->user->id);
        // Asking again finds the stored unlock and must not log the unlock a second time.
        $cond->is_available(false, $info, true, $this->user->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);
        $record = $DB->get_record('availability_oncemet', ['userid' => $this->user->id], '*', MUST_EXIST);

        $this->assertInstanceOf(unlock_gained::class, $event);
        $this->assertEquals(\context_module::instance($page->cmid), $event->get_context());
        $this->assertEquals($record->id, $event->objectid);
        $this->assertEquals($this->user->id, $event->relateduserid);
        $this->assertEquals($this->course->id, $event->courseid);
        $this->assertSame(
            ['cmid' => (int) $page->cmid, 'sectionid' => 0, 'instanceid' => self::INSTANCEID],
            $event->other
        );
        $this->assertStringContainsString(self::INSTANCEID, $event->get_description());
        $this->assertEquals(unlocks::get_report_url($page->cmid, 0, self::INSTANCEID), $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Tests that gaining an unlock on a course section is logged in the course context.
     */
    public function test_unlock_gained_on_section(): void {
        $section = get_fast_modinfo($this->course, $this->user->id)->get_section_info(1);
        $info = new info_section($section);
        $cond = $this->create_condition();

        $sink = $this->redirectEvents();
        $cond->is_available(false, $info, true, $this->user->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(unlock_gained::class, $event);
        $this->assertEquals(\context_course::instance($this->course->id), $event->get_context());
        $this->assertSame(
            ['cmid' => 0, 'sectionid' => (int) $section->id, 'instanceid' => self::INSTANCEID],
            $event->other
        );
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Tests that no event is triggered when the restriction is not met.
     */
    public function test_unlock_gained_not_triggered_when_not_met(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student', ['email' => 'other@bar.de']);
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);
        $cm = get_fast_modinfo($this->course, $other->id)->get_cm($page->cmid);

        $sink = $this->redirectEvents();
        $this->create_condition()->is_available(false, new info_module($cm), true, $other->id);
        $this->assertEquals(0, $sink->count());
        $sink->close();
    }

    /**
     * Tests that resetting unlocks on an activity logs one event per user whose unlock was removed.
     */
    public function test_unlock_reset_on_activity(): void {
        global $DB;

        $generator = $this->getDataGenerator();
        $teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $other = $generator->create_and_enrol($this->course, 'student');
        $without = $generator->create_and_enrol($this->course, 'student');
        $page = $generator->create_module('page', ['course' => $this->course->id]);

        $this->create_unlock(['cmid' => $page->cmid], $this->user->id);
        $this->create_unlock(['cmid' => $page->cmid], $other->id);
        $records = $DB->get_records_menu('availability_oncemet', null, '', 'userid, id');

        $this->setUser($teacher);
        $sink = $this->redirectEvents();
        // The user without an unlock is part of the selection, but must not show up in the logs.
        $removed = unlocks::reset($page->cmid, 0, self::INSTANCEID, [$this->user->id, $other->id, $without->id]);
        $events = $sink->get_events();
        $sink->close();

        $this->assertEquals(2, $removed);
        $this->assertCount(2, $events);

        $relateduserids = [];
        foreach ($events as $event) {
            $this->assertInstanceOf(unlock_reset::class, $event);
            $this->assertEquals(\context_module::instance($page->cmid), $event->get_context());
            $this->assertEquals($teacher->id, $event->userid);
            $this->assertEquals($records[$event->relateduserid], $event->objectid);
            $this->assertSame(
                ['cmid' => (int) $page->cmid, 'sectionid' => 0, 'instanceid' => self::INSTANCEID],
                $event->other
            );
            $this->assertStringContainsString(self::INSTANCEID, $event->get_description());
            $this->assertEventContextNotUsed($event);
            $relateduserids[] = $event->relateduserid;
        }

        sort($relateduserids);
        $expected = [$this->user->id, $other->id];
        sort($expected);
        $this->assertEquals($expected, $relateduserids);
    }

    /**
     * Tests that resetting an unlock on a course section is logged in the course context.
     */
    public function test_unlock_reset_on_section(): void {
        $section = get_fast_modinfo($this->course)->get_section_info(1);
        $this->create_unlock(['courseid' => $this->course->id, 'section' => 1], $this->user->id);

        $this->setAdminUser();
        $sink = $this->redirectEvents();
        unlocks::reset(0, $section->id, self::INSTANCEID, [$this->user->id]);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $event = reset($events);

        $this->assertInstanceOf(unlock_reset::class, $event);
        $this->assertEquals(\context_course::instance($this->course->id), $event->get_context());
        $this->assertEquals($this->user->id, $event->relateduserid);
        $this->assertSame(
            ['cmid' => 0, 'sectionid' => (int) $section->id, 'instanceid' => self::INSTANCEID],
            $event->other
        );
        $this->assertEquals(unlocks::get_report_url(0, $section->id, self::INSTANCEID), $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Tests that a reset which removes nothing logs nothing.
     */
    public function test_unlock_reset_not_triggered_without_unlocks(): void {
        $page = $this->getDataGenerator()->create_module('page', ['course' => $this->course->id]);

        $sink = $this->redirectEvents();
        $this->assertEquals(0, unlocks::reset($page->cmid, 0, self::INSTANCEID, [$this->user->id]));
        $this->assertEquals(0, $sink->count());
        $sink->close();
    }

    /**
     * Data provider for {@see self::test_validate_data()}.
     *
     * @return array
     */
    public static function validate_data_provider(): array {
        $complete = [
            'relateduserid' => 2,
            'other' => ['cmid' => 0, 'sectionid' => 1, 'instanceid' => self::INSTANCEID],
        ];

        $withoutother = function (string $key) use ($complete): array {
            unset($complete['other'][$key]);
            return $complete;
        };

        $withoutuser = $complete;
        unset($withoutuser['relateduserid']);

        $cases = [];
        foreach ([unlock_gained::class, unlock_reset::class] as $class) {
            $name = substr($class, strrpos($class, '\\') + 1);
            $cases[$name . ' without related user'] = [$class, $withoutuser, 'relateduserid'];
            $cases[$name . ' without other'] = [$class, ['relateduserid' => 2], 'cmid'];
            foreach (['cmid', 'sectionid', 'instanceid'] as $key) {
                $cases[$name . ' without ' . $key] = [$class, $withoutother($key), $key];
            }
        }

        return $cases;
    }

    /**
     * Tests that the events refuse to be created without the data which they are about.
     *
     * @param string $class Event class.
     * @param array $data Event data, without the context.
     * @param string $missing Name of the value which is missing.
     * @dataProvider validate_data_provider
     */
    public function test_validate_data(string $class, array $data, string $missing): void {
        $this->expectException(\coding_exception::class);
        $this->expectExceptionMessage('\'' . $missing . '\'');

        $class::create($data + ['context' => \context_course::instance($this->course->id)]);
    }

    /**
     * Tests that the logs of the events are mapped to the restored course when it is restored.
     *
     * This goes through a real backup and restore, as only the restore of the logs tells whether the
     * mappings name the restore mappings which it really keeps.
     */
    public function test_restore_mapping(): void {
        global $DB;

        $this->preventResetByRollback();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('buffersize', 0, 'logstore_standard');
        get_log_manager(true);

        $generator = $this->getDataGenerator();
        $teacher = $generator->create_and_enrol($this->course, 'editingteacher');
        $page = $generator->create_module('page', ['course' => $this->course->id]);
        $section = get_fast_modinfo($this->course)->get_section_info(1);

        // An unlock is gained on the activity and one on the section, and both are reset again, which
        // logs both events on both kinds of item. The learner is logged in while gaining the unlocks,
        // as the log store skips what happens without a logged in user.
        $this->setUser($this->user);
        $modinfo = get_fast_modinfo($this->course, $this->user->id);
        $cond = $this->create_condition();
        $cond->is_available(false, new info_module($modinfo->get_cm($page->cmid)), true, $this->user->id);
        $cond->is_available(false, new info_section($modinfo->get_section_info(1)), true, $this->user->id);
        $this->setUser($teacher);
        unlocks::reset($page->cmid, 0, self::INSTANCEID, [$this->user->id]);
        unlocks::reset(0, $section->id, self::INSTANCEID, [$this->user->id]);

        $this->setAdminUser();
        $newcourseid = $this->restore($this->backup($this->course), $this->course);

        $newcmid = (int) $DB->get_field('course_modules', 'id', ['course' => $newcourseid], MUST_EXIST);
        $newsectionid = (int) $DB->get_field('course_sections', 'id', ['course' => $newcourseid, 'section' => 1], MUST_EXIST);

        // Without new ids, the comparison below could not tell a mapped id from one kept as it was.
        $this->assertNotEquals($page->cmid, $newcmid);
        $this->assertNotEquals($section->id, $newsectionid);

        $logs = $DB->get_records('logstore_standard_log', [
            'courseid' => $newcourseid,
            'component' => 'availability_oncemet',
        ]);
        $this->assertCount(4, $logs);

        $found = [];
        foreach ($logs as $log) {
            $other = \logstore_standard\log\store::decode_other($log->other);
            $found[] = $log->eventname . ($other['cmid'] ? ' on activity' : ' on section');

            // The unlocks themselves are not backed up, so there is nothing the object id could point at.
            $this->assertEquals(\core\event\base::NOT_MAPPED, $log->objectid);
            $this->assertEquals($this->user->id, $log->relateduserid);
            // The restore hands out the new ids as read from the database, so they are compared by value.
            if ($other['cmid']) {
                $this->assertEquals(['cmid' => $newcmid, 'sectionid' => 0, 'instanceid' => self::INSTANCEID], $other);
            } else {
                $this->assertEquals(['cmid' => 0, 'sectionid' => $newsectionid, 'instanceid' => self::INSTANCEID], $other);
            }
        }

        sort($found);
        $this->assertEquals([
            '\availability_oncemet\event\unlock_gained on activity',
            '\availability_oncemet\event\unlock_gained on section',
            '\availability_oncemet\event\unlock_reset on activity',
            '\availability_oncemet\event\unlock_reset on section',
        ], $found);
    }

    /**
     * Backs a course up, including its users and its logs.
     *
     * @param \stdClass $course Course.
     * @return string Backup id.
     */
    protected function backup(\stdClass $course): string {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        // Turn off file logging, otherwise the backup cannot delete its files on Windows.
        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $bc->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $bc->get_plan()->get_setting('users')->set_value(true);
        $bc->get_plan()->get_setting('logs')->set_value(true);
        $backupid = $bc->get_backupid();

        $bc->execute_plan();
        $bc->destroy();

        return $backupid;
    }

    /**
     * Restores a backup into a new course, including its users and its logs.
     *
     * @param string $backupid Backup id.
     * @param \stdClass $course Course which was backed up.
     * @return int Id of the new course.
     */
    protected function restore(string $backupid, \stdClass $course): int {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $newcourseid = \restore_dbops::create_new_course(
            $course->fullname . ' restored',
            $course->shortname . '_restored',
            $course->category
        );
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $rc->get_plan()->get_setting('logs')->set_value(true);
        $rc->get_plan()->get_setting('users')->set_value(true);

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
