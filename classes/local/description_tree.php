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
 * Availability OnceMet - Description tree
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_oncemet\local;

use core_availability\info;
use core_availability\tree;

/**
 * Availability OnceMet - Description tree
 *
 * Learners are shown the nested restrictions of a Once met restriction as if these restrictions had
 * been added to the activity or section directly. To achieve that, the nested restrictions have to
 * be described exactly as core would describe them at the position of the Once met restriction.
 *
 * Core offers that description through tree::get_full_information(), but only for root trees and
 * without a way to hand down a NOT which is in effect. This subclass therefore exposes the
 * recursive method which does the actual work.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class description_tree extends tree {
    /**
     * Describes this tree as if it was placed at the given position of an availability tree.
     *
     * @param bool $not True if a NOT is in effect for this tree.
     * @param info $info Item we're checking.
     * @param bool $root True if this tree is the only restriction of the item.
     * @return string|\core_availability_multiple_messages Information about the restrictions.
     */
    public function describe(bool $not, info $info, bool $root) {
        return $this->get_full_information_recursive($not, $info, null, $root);
    }
}
