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

namespace availability_oncemet\local;

/**
 * Availability OnceMet - Unit tests for the unlock report helpers.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_oncemet\local\unlocks
 */
final class unlocks_test extends \advanced_testcase {
    /** @var string Once met instance id which the unlocks of these tests belong to. */
    const INSTANCEID = 'UNLOCK1';

    /** @var \stdClass Test course, which runs in separate groups mode. */
    protected \stdClass $course;

    /** @var \stdClass Activity which the Once met restriction sits on. */
    protected \stdClass $page;

    /** @var \stdClass Teacher who may not access all groups and is a member of group 1. */
    protected \stdClass $teacher;

    /** @var \stdClass[] Groups of the course, keyed by 1 and 2. */
    protected array $groups;

    /** @var \stdClass[] Learners who hold an unlock: 1 is in group 1, 2 is in group 2 and 3 is in no group. */
    protected array $learners;

    /**
     * Creates a course in separate groups mode with unlocks of learners in different groups.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course(['groupmode' => SEPARATEGROUPS]);
        $this->page = $generator->create_module('page', ['course' => $this->course->id]);

        // The non-editing teacher is the role of core which does not hold moodle/site:accessallgroups.
        $this->teacher = $generator->create_and_enrol($this->course, 'teacher');

        $this->groups = [
            1 => $generator->create_group(['courseid' => $this->course->id]),
            2 => $generator->create_group(['courseid' => $this->course->id]),
        ];
        $generator->create_group_member(['groupid' => $this->groups[1]->id, 'userid' => $this->teacher->id]);

        $oncemetgenerator = $generator->get_plugin_generator('availability_oncemet');
        for ($i = 1; $i <= 3; $i++) {
            $this->learners[$i] = $generator->create_and_enrol($this->course, 'student');
            $oncemetgenerator->create_unlock([
                'userid' => $this->learners[$i]->id,
                'cmid' => $this->page->cmid,
                'instanceid' => self::INSTANCEID,
            ]);
        }
        $generator->create_group_member(['groupid' => $this->groups[1]->id, 'userid' => $this->learners[1]->id]);
        $generator->create_group_member(['groupid' => $this->groups[2]->id, 'userid' => $this->learners[2]->id]);
    }

    /**
     * Returns the ids of the users whose unlocks are found for the given groups.
     *
     * @param int[]|null $groupids Groups to limit the unlocks to, null for all of them.
     * @return int[] User ids, sorted.
     */
    protected function get_unlocked_userids(?array $groupids): array {
        $records = unlocks::get_records($this->page->cmid, 0, self::INSTANCEID, '', [], '', $groupids);
        $userids = array_map('intval', array_column($records, 'userid'));
        sort($userids);

        return $userids;
    }

    /**
     * Tests that the report is only limited for a user who may not access all groups, and only in a
     * course which runs in separate groups mode.
     */
    public function test_get_visible_groupids(): void {
        global $DB;

        $context = \context_module::instance($this->page->cmid);

        // A user who may not access all groups is limited to the own groups.
        $this->setUser($this->teacher);
        $this->assertSame([(int) $this->groups[1]->id], unlocks::get_visible_groupids($this->course, $context));

        // A user who may access all groups is not limited at all.
        $editingteacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($editingteacher);
        $this->assertNull(unlocks::get_visible_groupids($this->course, $context));

        // A user who is in none of the groups is limited to nobody.
        $loneteacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->setUser($loneteacher);
        $this->assertSame([], unlocks::get_visible_groupids($this->course, $context));

        // Visible groups do not keep anybody apart, and neither does a course without groups.
        $this->setUser($this->teacher);
        foreach ([VISIBLEGROUPS, NOGROUPS] as $groupmode) {
            $DB->set_field('course', 'groupmode', $groupmode, ['id' => $this->course->id]);
            $this->assertNull(unlocks::get_visible_groupids(get_course($this->course->id), $context));
        }
    }

    /**
     * Tests that it is the group mode of the course which counts, not the one of the activity.
     */
    public function test_get_visible_groupids_ignores_activity_groupmode(): void {
        global $DB;

        $this->setUser($this->teacher);

        // A forum which is set to no groups in a course with separate groups still limits the report.
        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'groupmode' => NOGROUPS,
        ]);
        $context = \context_module::instance($forum->cmid);
        $this->assertSame([(int) $this->groups[1]->id], unlocks::get_visible_groupids($this->course, $context));

        // A forum which is set to separate groups in a course without groups does not limit it.
        $DB->set_field('course', 'groupmode', NOGROUPS, ['id' => $this->course->id]);
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $forum->cmid]);
        $this->assertNull(unlocks::get_visible_groupids(get_course($this->course->id), $context));
    }

    /**
     * Tests that the unlocks are limited to the members of the given groups.
     */
    public function test_get_records_limited_to_groups(): void {
        // Without a limit, everybody is listed, including the learner who is in no group at all.
        $this->assertCount(3, $this->get_unlocked_userids(null));

        $this->assertSame([(int) $this->learners[1]->id], $this->get_unlocked_userids([$this->groups[1]->id]));
        $this->assertSame([(int) $this->learners[2]->id], $this->get_unlocked_userids([$this->groups[2]->id]));
        $this->assertCount(2, $this->get_unlocked_userids([$this->groups[1]->id, $this->groups[2]->id]));

        // An empty list of groups stands for a user who is in none of them, not for no limit.
        $this->assertSame([], $this->get_unlocked_userids([]));
    }

    /**
     * Tests that the group limit goes together with an additional select clause.
     */
    public function test_get_records_limited_to_groups_with_where(): void {
        $records = unlocks::get_records(
            $this->page->cmid,
            0,
            self::INSTANCEID,
            'u.id = :wanteduserid',
            ['wanteduserid' => $this->learners[2]->id],
            'u.lastname ASC',
            [$this->groups[1]->id]
        );
        $this->assertSame([], $records);

        $records = unlocks::get_records(
            $this->page->cmid,
            0,
            self::INSTANCEID,
            'u.id = :wanteduserid',
            ['wanteduserid' => $this->learners[1]->id],
            'u.lastname ASC',
            [$this->groups[1]->id]
        );
        $this->assertCount(1, $records);
    }

    /**
     * Tests that a reset leaves the unlocks of users outside of the given groups alone.
     */
    public function test_reset_limited_to_groups(): void {
        $this->setUser($this->teacher);
        $alluserids = array_column($this->learners, 'id');

        // Somebody who is in none of the groups cannot reset anything, whatever is asked for.
        $this->assertSame(0, unlocks::reset($this->page->cmid, 0, self::INSTANCEID, $alluserids, []));
        $this->assertCount(3, $this->get_unlocked_userids(null));

        // A request which names everybody only reaches the members of the given group.
        $removed = unlocks::reset($this->page->cmid, 0, self::INSTANCEID, $alluserids, [$this->groups[1]->id]);
        $this->assertSame(1, $removed);
        $expected = [(int) $this->learners[2]->id, (int) $this->learners[3]->id];
        sort($expected);
        $this->assertSame($expected, $this->get_unlocked_userids(null));

        // Without a limit, the reset reaches everybody who was asked for.
        $this->assertSame(2, unlocks::reset($this->page->cmid, 0, self::INSTANCEID, $alluserids));
        $this->assertSame([], $this->get_unlocked_userids(null));
    }
}
