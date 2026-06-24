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

namespace availability_oncemet\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Availability OnceMet - Unit tests for the privacy provider.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass First test course. */
    protected \stdClass $course1;

    /** @var \stdClass Second test course. */
    protected \stdClass $course2;

    /** @var \stdClass First test user. */
    protected \stdClass $user1;

    /** @var \stdClass Second test user. */
    protected \stdClass $user2;

    /** @var \stdClass Third test user without any unlock record. */
    protected \stdClass $user3;

    /** @var \stdClass Page activity in the first course. */
    protected \stdClass $page1;

    /** @var \stdClass Page activity in the second course. */
    protected \stdClass $page2;

    /** @var \stdClass Section in the first course. */
    protected \stdClass $section1;

    /**
     * Creates courses, users, activities and unlock records.
     */
    protected function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        // The database is rolled back by resetAfterTest(), but static state is not.
        \availability_oncemet\condition::wipe_static_cache();

        $generator = $this->getDataGenerator();

        $this->course1 = $generator->create_course();
        $this->course2 = $generator->create_course();

        $this->user1 = $generator->create_user();
        $this->user2 = $generator->create_user();
        $this->user3 = $generator->create_user();

        $this->page1 = $generator->create_module('page', ['course' => $this->course1->id]);
        $this->page2 = $generator->create_module('page', ['course' => $this->course2->id]);

        $this->section1 = $DB->get_record('course_sections', ['course' => $this->course1->id, 'section' => 1], '*', MUST_EXIST);

        // User 1 has an unlock on the activity in course 1, on the section in course 1 and on the activity in course 2.
        $this->create_unlock($this->user1->id, ['cmid' => $this->page1->cmid], 'instance-cm1');
        $this->create_unlock($this->user1->id, ['courseid' => $this->course1->id, 'section' => 1], 'instance-sec1');
        $this->create_unlock($this->user1->id, ['cmid' => $this->page2->cmid], 'instance-cm2');

        // User 2 has an unlock on the activity in course 1 only.
        $this->create_unlock($this->user2->id, ['cmid' => $this->page1->cmid], 'instance-cm1');

        // User 3 has no unlock records at all.
    }

    /**
     * Creates an unlock record.
     *
     * This goes through the data generator of the plugin, which is the same one the Behat steps
     * build on, see {@see \behat_availability_oncemet_generator}.
     *
     * @param int $userid User id.
     * @param array $item Item the unlock belongs to, either a 'cmid' or a 'courseid' and a 'section'.
     * @param string $instanceid Once met instance id.
     */
    protected function create_unlock(int $userid, array $item, string $instanceid): void {
        $this->getDataGenerator()->get_plugin_generator('availability_oncemet')->create_unlock($item + [
            'userid' => $userid,
            'instanceid' => $instanceid,
        ]);
    }

    /**
     * Returns the userlist of the given context.
     *
     * @param \context $context Context to inspect.
     * @return userlist
     */
    protected function get_userlist(\context $context): userlist {
        $userlist = new userlist($context, 'availability_oncemet');
        provider::get_users_in_context($userlist);
        return $userlist;
    }

    /**
     * Tests that the plugin declares its database table as metadata.
     *
     * @covers \availability_oncemet\privacy\provider::get_metadata
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('availability_oncemet'));
        $items = $collection->get_collection();

        $this->assertCount(1, $items);

        $table = reset($items);
        $this->assertInstanceOf(\core_privacy\local\metadata\types\database_table::class, $table);
        $this->assertEquals('availability_oncemet', $table->get_name());
        $this->assertEquals('privacy:metadata:availability_oncemet', $table->get_summary());
        $this->assertEqualsCanonicalizing(
            ['userid', 'courseid', 'cmid', 'sectionid', 'availabilityuuid', 'timecreated'],
            array_keys($table->get_privacy_fields())
        );
    }

    /**
     * Tests that activity unlocks are reported in the activity context and section unlocks in the course context.
     *
     * @covers \availability_oncemet\privacy\provider::get_contexts_for_userid
     */
    public function test_get_contexts_for_userid(): void {
        $contextlist = provider::get_contexts_for_userid($this->user1->id);
        $this->assertEqualsCanonicalizing(
            [
                \context_module::instance($this->page1->cmid)->id,
                \context_course::instance($this->course1->id)->id,
                \context_module::instance($this->page2->cmid)->id,
            ],
            array_values($contextlist->get_contextids())
        );

        $contextlist = provider::get_contexts_for_userid($this->user2->id);
        $this->assertEquals(
            [\context_module::instance($this->page1->cmid)->id],
            array_values($contextlist->get_contextids())
        );

        $contextlist = provider::get_contexts_for_userid($this->user3->id);
        $this->assertCount(0, $contextlist);
    }

    /**
     * Tests that all users with unlocks in a context are found.
     *
     * @covers \availability_oncemet\privacy\provider::get_users_in_context
     */
    public function test_get_users_in_context(): void {
        $userlist = $this->get_userlist(\context_module::instance($this->page1->cmid));
        $this->assertEqualsCanonicalizing([$this->user1->id, $this->user2->id], $userlist->get_userids());

        $userlist = $this->get_userlist(\context_module::instance($this->page2->cmid));
        $this->assertEquals([$this->user1->id], $userlist->get_userids());

        // The course context must only report the section unlock, not the activity unlocks within the course.
        $userlist = $this->get_userlist(\context_course::instance($this->course1->id));
        $this->assertEquals([$this->user1->id], $userlist->get_userids());

        $userlist = $this->get_userlist(\context_course::instance($this->course2->id));
        $this->assertCount(0, $userlist);
    }

    /**
     * Tests that unsupported contexts are ignored.
     *
     * @covers \availability_oncemet\privacy\provider::get_users_in_context
     */
    public function test_get_users_in_unsupported_context(): void {
        $userlist = $this->get_userlist(\context_user::instance($this->user1->id));
        $this->assertCount(0, $userlist);

        $userlist = $this->get_userlist(\context_system::instance());
        $this->assertCount(0, $userlist);
    }

    /**
     * Tests that the unlock records of a user are exported into the correct contexts.
     *
     * @covers \availability_oncemet\privacy\provider::export_user_data
     */
    public function test_export_user_data(): void {
        $cmcontext = \context_module::instance($this->page1->cmid);
        $coursecontext = \context_course::instance($this->course1->id);
        $subcontext = [get_string('pluginname', 'availability_oncemet')];

        writer::reset();
        provider::export_user_data(new approved_contextlist(
            $this->user1,
            'availability_oncemet',
            [$cmcontext->id, $coursecontext->id]
        ));

        // The activity context contains the activity unlock only.
        /** @var \core_privacy\tests\request\content_writer $cmwriter */
        $cmwriter = writer::with_context($cmcontext);
        $data = $cmwriter->get_data($subcontext);
        $this->assertCount(1, $data->unlocks);
        $this->assertEquals('instance-cm1', $data->unlocks[0]->instanceid);
        $this->assertEquals($this->page1->cmid, $data->unlocks[0]->cmid);
        $this->assertEquals(0, $data->unlocks[0]->sectionid);
        $this->assertNotEmpty($data->unlocks[0]->timecreated);

        // The course context contains the section unlock only.
        /** @var \core_privacy\tests\request\content_writer $coursewriter */
        $coursewriter = writer::with_context($coursecontext);
        $data = $coursewriter->get_data($subcontext);
        $this->assertCount(1, $data->unlocks);
        $this->assertEquals('instance-sec1', $data->unlocks[0]->instanceid);
        $this->assertEquals($this->section1->id, $data->unlocks[0]->sectionid);
        $this->assertEquals(0, $data->unlocks[0]->cmid);
    }

    /**
     * Tests that nothing is exported for a user without unlock records.
     *
     * @covers \availability_oncemet\privacy\provider::export_user_data
     */
    public function test_export_user_data_without_records(): void {
        $cmcontext = \context_module::instance($this->page1->cmid);

        writer::reset();
        provider::export_user_data(new approved_contextlist($this->user3, 'availability_oncemet', [$cmcontext->id]));

        /** @var \core_privacy\tests\request\content_writer $cmwriter */
        $cmwriter = writer::with_context($cmcontext);
        $this->assertFalse($cmwriter->has_any_data());
    }

    /**
     * Tests that deleting an activity context removes the unlocks of all users in that activity.
     *
     * @covers \availability_oncemet\privacy\provider::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_module_context(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context(\context_module::instance($this->page1->cmid));

        $this->assertEquals(0, $DB->count_records('availability_oncemet', ['cmid' => $this->page1->cmid]));

        // The section unlock in the same course and the unlock in the other course are untouched.
        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['sectionid' => $this->section1->id]));
        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['cmid' => $this->page2->cmid]));
    }

    /**
     * Tests that deleting a course context removes the section unlocks but keeps the activity unlocks.
     *
     * @covers \availability_oncemet\privacy\provider::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_course_context(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context(\context_course::instance($this->course1->id));

        $this->assertEquals(0, $DB->count_records('availability_oncemet', ['sectionid' => $this->section1->id]));
        $this->assertEquals(2, $DB->count_records('availability_oncemet', ['cmid' => $this->page1->cmid]));
    }

    /**
     * Tests that unsupported contexts do not delete anything.
     *
     * @covers \availability_oncemet\privacy\provider::delete_data_for_all_users_in_context
     */
    public function test_delete_data_for_all_users_in_unsupported_context(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertEquals(4, $DB->count_records('availability_oncemet'));
    }

    /**
     * Tests that only the unlocks of the requested user in the requested contexts are deleted.
     *
     * @covers \availability_oncemet\privacy\provider::delete_data_for_user
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        provider::delete_data_for_user(new approved_contextlist(
            $this->user1,
            'availability_oncemet',
            [
                \context_module::instance($this->page1->cmid)->id,
                \context_course::instance($this->course1->id)->id,
            ]
        ));

        // Both unlocks of user 1 in course 1 are gone.
        $this->assertEquals(0, $DB->count_records('availability_oncemet', [
            'userid' => $this->user1->id,
            'courseid' => $this->course1->id,
        ]));

        // The unlock of user 1 in course 2 and the unlock of user 2 in course 1 remain.
        $this->assertEquals(1, $DB->count_records('availability_oncemet', [
            'userid' => $this->user1->id,
            'cmid' => $this->page2->cmid,
        ]));
        $this->assertEquals(1, $DB->count_records('availability_oncemet', ['userid' => $this->user2->id]));
    }

    /**
     * Tests that unsupported contexts do not delete anything for the requested user.
     *
     * @covers \availability_oncemet\privacy\provider::delete_data_for_user
     */
    public function test_delete_data_for_user_in_unsupported_context(): void {
        global $DB;

        provider::delete_data_for_user(new approved_contextlist(
            $this->user1,
            'availability_oncemet',
            [\context_system::instance()->id]
        ));

        $this->assertEquals(4, $DB->count_records('availability_oncemet'));
    }

    /**
     * Tests that only the unlocks of the requested users in the requested context are deleted.
     *
     * @covers \availability_oncemet\privacy\provider::delete_data_for_users
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $cmcontext = \context_module::instance($this->page1->cmid);
        provider::delete_data_for_users(new approved_userlist(
            $cmcontext,
            'availability_oncemet',
            [$this->user2->id]
        ));

        $this->assertEquals(0, $DB->count_records('availability_oncemet', [
            'userid' => $this->user2->id,
        ]));

        // All unlocks of user 1 remain in place.
        $this->assertEquals(3, $DB->count_records('availability_oncemet', ['userid' => $this->user1->id]));
    }

    /**
     * Tests that deleting users in an unsupported context does not delete anything.
     *
     * @covers \availability_oncemet\privacy\provider::delete_data_for_users
     */
    public function test_delete_data_for_users_in_unsupported_context(): void {
        global $DB;

        provider::delete_data_for_users(new approved_userlist(
            \context_system::instance(),
            'availability_oncemet',
            [$this->user1->id, $this->user2->id]
        ));

        $this->assertEquals(4, $DB->count_records('availability_oncemet'));
    }
}
