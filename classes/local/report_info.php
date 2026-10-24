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
 * Availability OnceMet - Availability information of the unlock report
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_oncemet\local;

use availability_oncemet\condition;

/**
 * Availability OnceMet - Availability information of the unlock report
 *
 * The unlock report describes one single Once met restriction of an item, not all restrictions of
 * it, so it cannot go through \core_availability\info::get_full_information(). Conditions may only
 * use \core_availability\info::get_modinfo() while core describes or checks them, though, and core
 * only provides the modinfo within its own methods. The completion condition, for instance, fails
 * with a coding exception when it is described without it.
 *
 * This trait therefore provides the modinfo for the description of a single restriction, exactly
 * as get_full_information() does it for the whole tree. It is used by the subclasses of the core
 * classes for activities and course sections, see report_info_module and report_info_section.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait report_info {
    /**
     * Describes a single Once met restriction of this item for staff.
     *
     * The description still carries the placeholders which core uses for names that need
     * formatting, so it has to go through \core_availability\info::format_info() before output.
     *
     * @param condition $condition Once met restriction of this item.
     * @return string Description of the restriction.
     */
    public function describe_restriction(condition $condition): string {
        $this->modinfo = get_fast_modinfo($this->course);

        try {
            return $condition->get_description(true, false, $this);
        } finally {
            $this->modinfo = null;
        }
    }
}
