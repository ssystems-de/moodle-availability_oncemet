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
 * Availability OnceMet - Unlock report helpers
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_oncemet\local;

use availability_oncemet\condition;
use availability_oncemet\event\unlock_reset;

/**
 * Availability OnceMet - Unlock report helpers
 *
 * The unlock records of a Once met restriction are written and read by \availability_oncemet\condition
 * while it decides whether an item is available. This class holds the other view on the very same
 * records: the one which staff gets on the unlock report, where the unlocks of one Once met
 * restriction are listed and can be reset again, see unlocks.php.
 *
 * An unlock belongs to exactly one Once met restriction on exactly one activity or course section,
 * so every method here is given the identifier of the restriction together with the item it sits on.
 * The item is named by a course module id for an activity and by a course section id for a course
 * section, and the other one of the two is 0, which is how the records store it as well.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unlocks {
    /**
     * Returns the URL of the unlock report of a Once met restriction.
     *
     * @param int $cmid Course module id, 0 if the restriction sits on a course section.
     * @param int $sectionid Course section id, 0 if the restriction sits on an activity.
     * @param string $instanceid Once met instance id.
     * @param \moodle_url|null $returnurl Page which the report returns to, none to let it fall back
     *                                    to the course page.
     * @return \moodle_url
     */
    public static function get_report_url(
        int $cmid,
        int $sectionid,
        string $instanceid,
        ?\moodle_url $returnurl = null
    ): \moodle_url {
        $params = ['instanceid' => $instanceid];

        if ($cmid) {
            $params['cmid'] = $cmid;
        } else {
            $params['sectionid'] = $sectionid;
        }

        if ($returnurl !== null) {
            // Only the local part is handed over, as that is all the report reads back from the URL.
            $params['returnurl'] = $returnurl->out_as_local_url(false);
        }

        return new \moodle_url('/availability/condition/oncemet/unlocks.php', $params);
    }

    /**
     * Returns the groups which the unlock report of a course is limited to for the current user.
     *
     * A user who may not access all groups only gets to see the members of the own groups in a course
     * which runs in separate groups mode, and the unlock report is a list of users like any other.
     *
     * It is the group mode of the course which decides here, even for a restriction which sits on an
     * activity. Most of the items which are restricted are resources, which have no group mode of their
     * own, so the group mode of the activity would let the report go unfiltered in exactly the courses
     * which keep their groups apart. The question which is answered here is whom the user may see in
     * this course, which is what the participants page asks as well.
     *
     * @param \stdClass $course Course which the restriction belongs to.
     * @param \context $context Context of the item which the restriction sits on.
     * @return int[]|null Ids of the groups whose members may be shown, which is an empty list for a
     *                    user who is in none of the groups, or null if the report is not limited.
     */
    public static function get_visible_groupids(\stdClass $course, \context $context): ?array {
        global $USER;

        if (groups_get_course_groupmode($course) != SEPARATEGROUPS) {
            return null;
        }

        if (has_capability('moodle/site:accessallgroups', $context)) {
            return null;
        }

        // The groups of the course which the user is a member of. If the course has a default grouping,
        // only the groups of that grouping count, which is how core picks the groups of a course as well.
        $owngroups = groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid);

        // The groups come back keyed by their id, and the ids are all that the caller needs.
        return array_map('intval', array_keys($owngroups));
    }

    /**
     * Returns the unlocks which one Once met restriction holds, together with the users who hold them.
     *
     * @param int $cmid Course module id, 0 if the restriction sits on a course section.
     * @param int $sectionid Course section id, 0 if the restriction sits on an activity.
     * @param string $instanceid Once met instance id.
     * @param string $where Additional select clause, empty for all unlocks of the restriction.
     * @param array $whereparams Parameters of that select clause.
     * @param string $sort Order by clause, empty for the order of the database.
     * @param int[]|null $groupids Groups which the users have to be a member of, as returned by
     *                             {@see self::get_visible_groupids()}, or null for the users of all groups.
     * @return \stdClass[] Unlock records, keyed by their id, carrying the user id, the name fields of
     *                     the user and the time of the unlock as 'timeunlocked'.
     */
    public static function get_records(
        int $cmid,
        int $sectionid,
        string $instanceid,
        string $where = '',
        array $whereparams = [],
        string $sort = '',
        ?array $groupids = null
    ): array {
        global $DB;

        // A user who is limited to the own groups and is in none of them gets to see nobody.
        if ($groupids === []) {
            return [];
        }

        if ($groupids !== null) {
            [$groupsql, $groupparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'group');
            $groupwhere = 'EXISTS (SELECT 1
                                     FROM {groups_members} gm
                                    WHERE gm.userid = o.userid AND gm.groupid ' . $groupsql . ')';
            $where = $where !== '' ? $where . ' AND ' . $groupwhere : $groupwhere;
            $whereparams += $groupparams;
        }

        // The whole set of name fields is read rather than just the two which the report shows, so
        // that the records can be handed to fullname() for the labels which screen readers get.
        $namefields = \core_user\fields::for_name()->get_sql('u');

        $params = $whereparams + $namefields->params + [
            'cmid' => $cmid,
            'sectionid' => $sectionid,
            'instanceid' => $instanceid,
        ];

        // The time of the unlock is deliberately not called timecreated here: the user table carries
        // a column of that name as well, and an order by clause which names it would be ambiguous.
        $sql = 'SELECT o.id, o.userid, o.timecreated AS timeunlocked ' . $namefields->selects . '
                  FROM {availability_oncemet} o
                  JOIN {user} u ON u.id = o.userid
                       ' . $namefields->joins . '
                 WHERE o.cmid = :cmid AND o.sectionid = :sectionid AND o.availabilityuuid = :instanceid
                       AND u.deleted = 0';

        if ($where !== '') {
            $sql .= ' AND ' . $where;
        }

        if ($sort !== '') {
            $sql .= ' ORDER BY ' . $sort;
        }

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Removes the unlocks which given users hold for one Once met restriction.
     *
     * The users have to fulfil the nested restrictions once more afterwards to gain the unlock again,
     * exactly as they had to before they gained it the first time. Nothing else about the restriction
     * changes, so the unlocks of everybody else stay in place.
     *
     * @param int $cmid Course module id, 0 if the restriction sits on a course section.
     * @param int $sectionid Course section id, 0 if the restriction sits on an activity.
     * @param string $instanceid Once met instance id.
     * @param int[] $userids Users whose unlock is to be reset.
     * @param int[]|null $groupids Groups which the users have to be a member of, as returned by
     *                             {@see self::get_visible_groupids()}, or null for the users of all groups.
     *                             The unlocks of users who are in none of these groups are left alone.
     * @return int Number of unlocks which were removed.
     */
    public static function reset(int $cmid, int $sectionid, string $instanceid, array $userids, ?array $groupids = null): int {
        global $DB;

        // Somebody who is limited to the own groups and is in none of them has nobody to reset.
        if (empty($userids) || $groupids === []) {
            return 0;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $params['cmid'] = $cmid;
        $params['sectionid'] = $sectionid;
        $params['instanceid'] = $instanceid;

        $select = 'cmid = :cmid AND sectionid = :sectionid AND availabilityuuid = :instanceid AND userid ' . $insql;

        // The user ids come straight from the request, so they are held against the groups once more
        // here: what the report does not show must not be within reach of a hand-made request either.
        if ($groupids !== null) {
            [$groupsql, $groupparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'group');
            $select .= ' AND userid IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid ' . $groupsql . ')';
            $params += $groupparams;
        }

        // Users who do not hold an unlock at all may well be among the selected ones, as the report
        // can be submitted from a page which somebody else has changed in the meantime, so what was
        // really removed is read rather than assumed. It is what is counted and logged below.
        $removed = $DB->get_records_select('availability_oncemet', $select, $params);
        $DB->delete_records_select('availability_oncemet', $select, $params);

        // The cache of \availability_oncemet\condition would otherwise keep handing out the access
        // which was just taken away for the rest of the request.
        condition::wipe_static_cache();

        // The reset can take the access to the item away from the users, so each of them gets an
        // entry in the logs which tells who did it.
        foreach ($removed as $record) {
            unlock_reset::create_from_record($record)->trigger();
        }

        return count($removed);
    }
}
