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
 * Availability OnceMet - Event handlers
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    // Clean up when the item which holds the restriction is deleted.
    [
        'eventname' => '\core\event\course_deleted',
        'callback' => '\availability_oncemet\observer::course_deleted',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\availability_oncemet\observer::course_module_deleted',
    ],
    [
        'eventname' => '\core\event\course_section_deleted',
        'callback' => '\availability_oncemet\observer::course_section_deleted',
    ],
    [
        'eventname' => '\core\event\user_deleted',
        'callback' => '\availability_oncemet\observer::user_deleted',
    ],

    // Clean up when a course is handed over to another group of users.
    [
        'eventname' => '\core\event\course_reset_ended',
        'callback' => '\availability_oncemet\observer::course_reset_ended',
    ],

    // Clean up when the restriction is removed from an item which still exists.
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\availability_oncemet\observer::course_module_updated',
    ],
    [
        'eventname' => '\core\event\course_section_updated',
        'callback' => '\availability_oncemet\observer::course_section_updated',
    ],
];
