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
 * Availability OnceMet - Base class of the events about a single unlock.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_oncemet\event;

use availability_oncemet\local\unlocks;

/**
 * Availability OnceMet - Base class of the events about a single unlock.
 *
 * Every event is about the unlock of exactly one user, who is the related user of the event. That way
 * the event turns up in the logs of that very user, and the privacy provider of the log stores finds
 * and deletes it together with the other log entries of that user, which would not happen with user
 * ids hidden in the other data.
 *
 * The event is triggered in the context in which the access to the item is checked, which is the
 * module context for an activity and the course context for a course section.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int cmid: Course module id, 0 if the restriction sits on a course section.
 *      - int sectionid: Course section id, 0 if the restriction sits on an activity.
 *      - string instanceid: Once met instance id.
 * }
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class unlock_base extends \core\event\base {
    /**
     * Creates the event from an unlock record.
     *
     * @param \stdClass $record Unlock record, carrying at least its id and its user, course, course
     *                          module, course section and instance ids.
     * @return static
     */
    public static function create_from_record(\stdClass $record): static {
        // Which of the two identifiers is set tells whether the restriction sits on an activity or
        // on a course section.
        if ($record->cmid) {
            $context = \context_module::instance($record->cmid);
        } else {
            $context = \context_course::instance($record->courseid);
        }

        return static::create([
            'objectid' => $record->id,
            'relateduserid' => $record->userid,
            'context' => $context,
            'other' => [
                'cmid' => (int) $record->cmid,
                'sectionid' => (int) $record->sectionid,
                'instanceid' => $record->availabilityuuid,
            ],
        ]);
    }

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'availability_oncemet';
    }

    /**
     * Returns the item which the restriction sits on, as it is named in the descriptions.
     *
     * @return string
     */
    protected function get_item_description(): string {
        if ($this->other['cmid']) {
            return "the course module with id '{$this->other['cmid']}'";
        }

        return "the course section with id '{$this->other['sectionid']}'";
    }

    /**
     * Returns the URL of the unlock report of the restriction.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return unlocks::get_report_url($this->other['cmid'], $this->other['sectionid'], $this->other['instanceid']);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        foreach (['cmid', 'sectionid', 'instanceid'] as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception('The \'' . $key . '\' value must be set in other.');
            }
        }
    }

    /**
     * Returns the mapping of the object id for restore.
     *
     * Unlocks are not backed up, so a restored course has no unlock record which the id could point at.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'availability_oncemet', 'restore' => self::NOT_MAPPED];
    }

    /**
     * Returns the mapping of the other data for restore.
     *
     * The instance id needs no mapping, as a Once met restriction keeps it when it is backed up and
     * restored.
     *
     * @return array
     */
    public static function get_other_mapping() {
        return [
            'cmid' => ['db' => 'course_modules', 'restore' => 'course_module'],
            'sectionid' => ['db' => 'course_sections', 'restore' => 'course_section'],
        ];
    }
}
