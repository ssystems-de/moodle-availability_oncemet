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
 * Availability OnceMet - Event which is triggered when a user gains a permanent unlock.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_oncemet\event;

/**
 * Availability OnceMet - Event which is triggered when a user gains a permanent unlock.
 *
 * An unlock is gained while availability is checked, which is not necessarily done in a request of
 * the user who gains it: a teacher who looks at a report or a scheduled task which walks through the
 * users of a course checks it as well. The user of the event is therefore left to be the user of the
 * request, as it is for the completion events of core, and the user who gained the unlock is the
 * related user. For the same reason, the event is not regarded as an action of a participant.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class unlock_gained extends unlock_base {
    /**
     * Init method.
     */
    protected function init() {
        parent::init();
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_unlockgained', 'availability_oncemet');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->relateduserid}' has gained the permanent unlock of the Once met " .
            "restriction with id '{$this->other['instanceid']}' on " . $this->get_item_description() . '.';
    }
}
