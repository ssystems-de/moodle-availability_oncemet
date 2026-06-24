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
 * Availability OnceMet - Privacy provider.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_oncemet\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementing provider.
 *
 * Unlock records which belong to an activity are reported within the context of that activity.
 * Unlock records which belong to a course section are reported within the context of the course,
 * as course sections do not have a context of their own.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised item collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'availability_oncemet',
            [
                'userid' => 'privacy:metadata:availability_oncemet:userid',
                'courseid' => 'privacy:metadata:availability_oncemet:courseid',
                'cmid' => 'privacy:metadata:availability_oncemet:cmid',
                'sectionid' => 'privacy:metadata:availability_oncemet:sectionid',
                'availabilityuuid' => 'privacy:metadata:availability_oncemet:availabilityuuid',
                'timecreated' => 'privacy:metadata:availability_oncemet:timecreated',
            ],
            'privacy:metadata:availability_oncemet'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // Unlock records which are stored for an activity.
        $sql = "SELECT cx.id
                  FROM {availability_oncemet} a
                  JOIN {context} cx ON cx.instanceid = a.cmid AND cx.contextlevel = :contextlevel
                 WHERE a.userid = :userid AND a.cmid <> 0";
        $contextlist->add_from_sql($sql, [
            'userid' => $userid,
            'contextlevel' => CONTEXT_MODULE,
        ]);

        // Unlock records which are stored for a course section (or for the course itself).
        $sql = "SELECT cx.id
                  FROM {availability_oncemet} a
                  JOIN {context} cx ON cx.instanceid = a.courseid AND cx.contextlevel = :contextlevel
                 WHERE a.userid = :userid AND a.cmid = 0";
        $contextlist->add_from_sql($sql, [
            'userid' => $userid,
            'contextlevel' => CONTEXT_COURSE,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (is_a($context, \context_module::class)) {
            $sql = "SELECT userid
                      FROM {availability_oncemet}
                     WHERE cmid = :cmid";
            $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
            return;
        }

        if (is_a($context, \context_course::class)) {
            $sql = "SELECT userid
                      FROM {availability_oncemet}
                     WHERE courseid = :courseid AND cmid = 0";
            $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $subcontext = [get_string('pluginname', 'availability_oncemet')];

        foreach ($contextlist->get_contexts() as $context) {
            [$select, $params] = self::get_context_select($context);
            if ($select === null) {
                continue;
            }

            $select .= ' AND userid = :userid';
            $params['userid'] = $userid;

            $records = $DB->get_records_select('availability_oncemet', $select, $params, 'timecreated ASC');
            if (empty($records)) {
                continue;
            }

            $unlocks = [];
            foreach ($records as $record) {
                $unlocks[] = (object) [
                    'instanceid' => $record->availabilityuuid,
                    'courseid' => $record->courseid,
                    'cmid' => $record->cmid,
                    'sectionid' => $record->sectionid,
                    'timecreated' => transform::datetime($record->timecreated),
                ];
            }

            writer::with_context($context)->export_data($subcontext, (object) ['unlocks' => $unlocks]);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        [$select, $params] = self::get_context_select($context);
        if ($select === null) {
            return;
        }

        $DB->delete_records_select('availability_oncemet', $select, $params);

        \availability_oncemet\condition::wipe_static_cache();
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            [$select, $params] = self::get_context_select($context);
            if ($select === null) {
                continue;
            }

            $select .= ' AND userid = :userid';
            $params['userid'] = $userid;

            $DB->delete_records_select('availability_oncemet', $select, $params);
        }

        \availability_oncemet\condition::wipe_static_cache();
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        [$select, $params] = self::get_context_select($context);
        if ($select === null) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'usr');
        $select .= " AND userid {$usersql}";
        $params += $userparams;

        $DB->delete_records_select('availability_oncemet', $select, $params);

        \availability_oncemet\condition::wipe_static_cache();
    }

    /**
     * Builds the SQL select clause which matches all unlock records of the given context.
     *
     * @param \context $context Context to build the select clause for.
     * @return array Select clause (or null if the context is not supported) and its parameters.
     */
    protected static function get_context_select(\context $context): array {
        if (is_a($context, \context_module::class)) {
            return ['cmid = :cmid', ['cmid' => $context->instanceid]];
        }

        if (is_a($context, \context_course::class)) {
            return ['courseid = :courseid AND cmid = 0', ['courseid' => $context->instanceid]];
        }

        return [null, []];
    }
}
