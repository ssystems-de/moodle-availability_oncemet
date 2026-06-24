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
 * Availability OnceMet - Description messages
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_oncemet\local;

/**
 * Availability OnceMet - Description messages
 *
 * The description of a Once met restriction is a list of messages, so that core renders the nested
 * restrictions as a list which is indented below the Once met label.
 *
 * Core appends the 'hidden otherwise' marker to the description of the only restriction of an
 * activity or course section with a plain string concatenation, see
 * core_availability\tree::get_full_information_recursive(). That works for the plain strings which
 * core's own conditions return, but a message list is an object and cannot be concatenated, which
 * ended in a fatal error as soon as such an item was set to be hidden entirely.
 *
 * This class therefore renders itself when it is used as a string. The nested restrictions stay
 * visible that way and the marker ends up behind them.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class description_messages extends \core_availability_multiple_messages {
    /**
     * Renders these messages exactly as core renders any list of availability messages.
     *
     * @return string
     */
    public function __toString(): string {
        global $OUTPUT;

        return $OUTPUT->render(new \core_availability\output\availability_info($this));
    }
}
