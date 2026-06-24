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
 * Availability OnceMet - Uninstallation script
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Uninstall the plugin.
 */
function xmldb_availability_oncemet_uninstall() {
    global $DB, $OUTPUT;

    $dbman = $DB->get_manager();

    // If the unlock table still exists.
    // Moodle drops the tables from db/install.xml on its own after this function has run, but we drop it
    // explicitly to make sure that no unlock record outlives the plugin even if that ever changes.
    $table = new xmldb_table('availability_oncemet');
    if ($dbman->table_exists($table)) {
        // Remove it.
        $dbman->drop_table($table);
    }

    // Count the activities and course sections which still hold a Once met restriction.
    // These are not touched here as removing them would silently change the access restrictions of courses.
    $like = $DB->sql_like('availability', ':pattern', false, false);
    $params = ['pattern' => '%"type":"oncemet"%'];
    $remaining = $DB->count_records_select('course_modules', $like, $params);
    $remaining += $DB->count_records_select('course_sections', $like, $params);

    // If there are any.
    if ($remaining > 0) {
        // Show a notification about that fact (this also looks fine in the CLI installer).
        $notification = new \core\output\notification(
            get_string('uninstaller_remainingrestrictions', 'availability_oncemet', $remaining),
            \core\output\notification::NOTIFY_WARNING
        );
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
    }
}
