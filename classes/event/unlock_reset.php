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
 * Availability OnceMet - Event which is triggered when the permanent unlock of a user is reset.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_oncemet\event;

/**
 * Availability OnceMet - Event which is triggered when the permanent unlock of a user is reset.
 *
 * The reset is done by staff on the unlock report, see unlocks.php, and can take the access to an
 * activity or course section away from the related user. One event is triggered for each user whose
 * unlock was really removed. The removal of unlocks which follows from the deletion of an item or of
 * a user, from a course reset or from the removal of the restriction is not covered, as these are the
 * consequence of actions which core logs on its own.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unlock_reset extends unlock_base {
    /**
     * Init method.
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_unlockreset', 'availability_oncemet');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' has reset the permanent unlock of the user with id " .
            "'{$this->relateduserid}' for the Once met restriction with id '{$this->other['instanceid']}' on " .
            $this->get_item_description() . '.';
    }
}
