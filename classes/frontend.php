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
 * Availability OnceMet - Frontend class
 *
 * @package    availability_oncemet
 * @copyright  2026 Mahmoud Chehada, ssystems GmbH <mchehada@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_oncemet;

use availability_oncemet\local\unlocks;

/**
 * Availability OnceMet - Frontend class
 *
 * @package    availability_oncemet
 * @copyright  2026 Mahmoud Chehada, ssystems GmbH <mchehada@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    /**
     * Strings required by the YUI form module.
     *
     * @return string[]
     */
    protected function get_javascript_strings() {
        return [
            'addrestriction',
            'confirmremove_continue',
            'confirmremove_message',
            'confirmremove_title',
            'error_nochildren',
            'helptext_persistent',
            'helptext_remove',
            'unlocks_button',
        ];
    }

    /**
     * Tells the form JavaScript which Once met restrictions of the edited item hold unlocks already.
     *
     * Removing a Once met restriction from an activity or course section deletes the unlocks which
     * users gained from it, see \availability_oncemet\observer. That is worth a warning, but only
     * where something can actually be lost, which is why the JavaScript is told exactly which
     * restrictions those are instead of being left to guess.
     *
     * The answer covers both halves of that question at once. Only the restrictions which are stored
     * on the item are looked at, so a restriction which the teacher has just added within the open
     * form is never part of it: its identifier is not in the availability field yet, and it cannot
     * have unlocks either. And of those stored restrictions, only the ones which really do have
     * unlock records are returned.
     *
     * The unlocks of the user who is editing are left out of that count, see
     * {@see self::get_unlocked_instance_ids()} for why they would otherwise warn about nothing.
     *
     * @param \stdClass $course Course.
     * @param \cm_info|null $cm Module.
     * @param \section_info|null $section Section.
     * @return array The identifiers of the Once met restrictions which hold unlocks, followed by the
     *               unlock report URL of every stored Once met restriction of the item.
     */
    protected function get_javascript_init_params($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        return [
            $this->get_unlocked_instance_ids($cm, $section),
            $this->get_report_urls($course, $cm, $section),
        ];
    }

    /**
     * Tells the form JavaScript where the unlock report of each stored Once met restriction of the
     * edited item can be found.
     *
     * Whether a Once met restriction has handed out permanent access, and to whom, is otherwise
     * nowhere to be seen while a teacher edits the restrictions of an activity or course section.
     * The button which these URLs build is what turns that into something which can be looked at,
     * see unlocks.php for the report behind it.
     *
     * Every stored restriction gets a URL, including the ones which nobody has unlocked yet: that a
     * restriction has handed out nothing so far is an answer of its own, and it is one which the
     * report gives without this having to ask the database for it here. Restrictions which the
     * teacher has just added within the open form are left out, as they are not stored yet and
     * therefore have no report to open.
     *
     * @param \stdClass $course Course.
     * @param \cm_info|null $cm Module.
     * @param \section_info|null $section Section.
     * @return string[] Report URL of each stored restriction, keyed by its instance id. Empty if the
     *                  user may not see the unlocks or if there is nothing to report on.
     */
    protected function get_report_urls(
        \stdClass $course,
        ?\cm_info $cm = null,
        ?\section_info $section = null
    ): array {
        global $PAGE;

        // An item which does not exist yet, as it is the case while an activity is being created,
        // cannot carry a stored restriction and therefore cannot hold an unlock either.
        if ($cm !== null) {
            $cmid = $cm->id;
            $sectionid = 0;
            $availability = $cm->availability;
        } else if ($section !== null) {
            $cmid = 0;
            $sectionid = $section->id;
            $availability = $section->availability;
        } else {
            return [];
        }

        if (!has_capability('availability/oncemet:viewunlocks', $this->get_edit_context($course, $cm, $section))) {
            return [];
        }

        // The report is opened from the form which is being rendered right now, so that form is the
        // page which its back button has to return to.
        $returnurl = $PAGE->has_set_url() ? $PAGE->url : null;

        $urls = [];
        foreach (condition::get_instance_ids($availability) as $instanceid) {
            // The URL is handed over unescaped, as the JavaScript sets it as an attribute value
            // rather than pasting it into a string of HTML.
            $urls[$instanceid] = unlocks::get_report_url($cmid, $sectionid, $instanceid, $returnurl)->out(false);
        }

        return $urls;
    }

    /**
     * Returns the identifiers of the Once met restrictions of an item which hold unlock records of
     * users other than the one who is editing.
     *
     * Availability is evaluated before Moodle checks whether the user is allowed to ignore access
     * restrictions at all, so a teacher who fulfils the nested restrictions gathers unlock records
     * just like everybody else, even though a Once met restriction never restricts them. Merely
     * having looked at the course page can therefore be enough to hold an unlock, and that unlock is
     * never used for anything. Counting it would mean asking every teacher who removes a restriction
     * to confirm that their own pointless record may go, which is exactly the noise that turns a
     * warning into something people click away without reading.
     *
     * @param \cm_info|null $cm Module.
     * @param \section_info|null $section Section.
     * @return string[] Instance identifiers, may be empty.
     */
    protected function get_unlocked_instance_ids(?\cm_info $cm = null, ?\section_info $section = null): array {
        global $DB, $USER;

        // An item which does not exist yet, as it is the case while an activity is being created,
        // cannot carry a stored restriction and therefore cannot hold an unlock either.
        if ($cm !== null) {
            $field = 'cmid';
            $id = $cm->id;
            $availability = $cm->availability;
        } else if ($section !== null) {
            $field = 'sectionid';
            $id = $section->id;
            $availability = $section->availability;
        } else {
            return [];
        }

        $instanceids = condition::get_instance_ids($availability);
        if (empty($instanceids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED, 'uuid');
        $params['itemid'] = $id;
        $params['editinguser'] = $USER->id;

        $unlocked = $DB->get_fieldset_select(
            'availability_oncemet',
            'DISTINCT availabilityuuid',
            $field . ' = :itemid AND userid <> :editinguser AND availabilityuuid ' . $insql,
            $params
        );

        // The identifiers are handed to JavaScript as a list, so the keys have to be a plain sequence.
        return array_values($unlocked);
    }

    /**
     * Hide the add button for users who may not add this restriction.
     *
     * @param \stdClass $course Course.
     * @param \cm_info|null $cm Module.
     * @param \section_info|null $section Section.
     * @return bool
     */
    protected function allow_add($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        return has_capability(
            'availability/oncemet:addinstance',
            $this->get_edit_context($course, $cm, $section)
        );
    }

    /**
     * Context for editing availability (activity module or course).
     *
     * @param \stdClass $course Course.
     * @param \cm_info|null $cm Module.
     * @param \section_info|null $section Section.
     * @return \context
     */
    protected function get_edit_context(\stdClass $course, ?\cm_info $cm = null, ?\section_info $section = null): \context {
        if ($cm !== null) {
            return $cm->context;
        }
        return \context_course::instance($course->id);
    }
}
