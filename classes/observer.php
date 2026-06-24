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
 * Availability OnceMet - Event observers
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_oncemet;

/**
 * Availability OnceMet - Event observers
 *
 * These keep the unlock table free of records which do not belong to an existing Once met restriction
 * anymore, may it be because the surrounding course, activity, section or user was deleted or because
 * the restriction itself was removed from an activity or section which still exists.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Removes the unlock records of a deleted course.
     *
     * Moodle deletes the activities and course sections of a course without triggering their own
     * deletion events, which is why this observer has to clean up the whole course on its own.
     *
     * @param \core\event\course_deleted $event The event.
     */
    public static function course_deleted(\core\event\course_deleted $event): void {
        self::delete_unlocks(['courseid' => $event->objectid]);
    }

    /**
     * Removes the unlock records of a deleted activity.
     *
     * @param \core\event\course_module_deleted $event The event.
     */
    public static function course_module_deleted(\core\event\course_module_deleted $event): void {
        self::delete_unlocks(['cmid' => $event->objectid]);
    }

    /**
     * Removes the unlock records of a deleted course section.
     *
     * @param \core\event\course_section_deleted $event The event.
     */
    public static function course_section_deleted(\core\event\course_section_deleted $event): void {
        self::delete_unlocks(['sectionid' => $event->objectid]);
    }

    /**
     * Removes the unlock records of a deleted user.
     *
     * @param \core\event\user_deleted $event The event.
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        self::delete_unlocks(['userid' => $event->objectid]);
    }

    /**
     * Removes the unlock records of a course which was reset for another group of users.
     *
     * An unlock states that a user met the nested restrictions once. A reset which wipes activity
     * completion or gradebook grades does not make that statement wrong, as what was met stays met.
     * It only becomes pointless once the reset hands the course over to somebody else, which is what
     * unenrolling the current users means, so that is the option this is tied to.
     *
     * Only the unlocks of the users who really did lose their enrolment are removed. Which users
     * those are cannot be taken from the event. It carries the options of the reset form, and the
     * roles which are selected there do not answer the question: Moodle only unenrols a user who is
     * left without any role in the course, everybody else just loses the selected role assignment,
     * see the unenrolment part of reset_course_userdata(). The list of unenrolled users which Moodle
     * builds there does not reach the event either, as the event data is assembled before the
     * unenrolments happen. The enrolments after the reset are therefore the only reliable source.
     *
     * A restriction of the course reset form is that only activity modules can add options of their
     * own to it, see the loop over the installed modules in course/reset_form.php. This plugin can
     * therefore not offer an option which teachers could tick on purpose.
     *
     * @param \core\event\course_reset_ended $event The event.
     */
    public static function course_reset_ended(\core\event\course_reset_ended $event): void {
        $options = $event->other['reset_options'] ?? [];

        if (empty($options['unenrol_users'])) {
            return;
        }

        $context = \context_course::instance($event->courseid, IGNORE_MISSING);
        if (!$context) {
            return;
        }

        // Suspended enrolments count as enrolments here. A suspension is meant to be temporary and
        // must not destroy an unlock which this plugin promises to keep permanently.
        [$enrolledsql, $params] = get_enrolled_sql($context);
        $params['oncemetcourseid'] = $event->courseid;

        self::delete_unlocks_select(
            'courseid = :oncemetcourseid AND userid NOT IN (' . $enrolledsql . ')',
            $params
        );
    }

    /**
     * Removes the unlock records of Once met restrictions which were removed from an activity.
     *
     * @param \core\event\course_module_updated $event The event.
     */
    public static function course_module_updated(\core\event\course_module_updated $event): void {
        global $DB;

        $availability = $DB->get_field('course_modules', 'availability', ['id' => $event->objectid]);

        self::remove_obsolete_unlocks('cmid', $event->objectid, $availability);
    }

    /**
     * Removes the unlock records of Once met restrictions which were removed from a course section.
     *
     * @param \core\event\course_section_updated $event The event.
     */
    public static function course_section_updated(\core\event\course_section_updated $event): void {
        global $DB;

        $availability = $DB->get_field('course_sections', 'availability', ['id' => $event->objectid]);

        self::remove_obsolete_unlocks('sectionid', $event->objectid, $availability);
    }

    /**
     * Removes all unlock records of an activity or course section which do not belong to one of the
     * Once met restrictions which the given availability field still holds.
     *
     * @param string $field Database field which identifies the context, either cmid or sectionid.
     * @param int $id Value of that database field.
     * @param string|bool $availability Raw availability field, false if the record does not exist anymore.
     */
    protected static function remove_obsolete_unlocks(string $field, int $id, $availability): void {
        global $DB;

        $instanceids = condition::get_instance_ids(is_string($availability) ? $availability : null);

        $select = $field . ' = :id';
        $params = ['id' => $id];

        // If there are any Once met restrictions left, their unlock records have to be kept.
        if (!empty($instanceids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED, 'uuid', false);
            $select .= ' AND availabilityuuid ' . $insql;
            $params += $inparams;
        }

        self::delete_unlocks_select($select, $params);
    }

    /**
     * Deletes unlock records and drops the cache which holds them.
     *
     * The cache of \availability_oncemet\condition would otherwise keep reporting unlocks which
     * were removed within the very same request, which happens whenever a teacher saves an activity
     * and is shown the course page afterwards.
     *
     * @param array $conditions Conditions of the records to delete, as field => value.
     */
    protected static function delete_unlocks(array $conditions): void {
        global $DB;

        $DB->delete_records('availability_oncemet', $conditions);

        condition::wipe_static_cache();
    }

    /**
     * Deletes the unlock records which match a select clause and drops the cache which holds them.
     *
     * @param string $select Select clause of the records to delete.
     * @param array $params Parameters of that select clause.
     */
    protected static function delete_unlocks_select(string $select, array $params): void {
        global $DB;

        $DB->delete_records_select('availability_oncemet', $select, $params);

        condition::wipe_static_cache();
    }
}
