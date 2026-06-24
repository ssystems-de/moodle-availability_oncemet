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
 * Availability OnceMet - Behat context.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Availability OnceMet - Behat context.
 *
 * Makes the unlock report of a Once met restriction reachable without going through the availability
 * form. The form is written in YUI, so reaching the report through its button needs a JavaScript
 * scenario, which is worth doing for the button itself but not for every scenario which is about
 * what the report does.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_availability_oncemet extends behat_base {
    /**
     * Resolves the URL of a page of this plugin.
     *
     * Recognised page types are:
     *
     * | Page type               | Identifier                          | Description                            |
     * | activity unlocks report | Activity idnumber > instance id     | Unlocks of a restriction on an activity |
     * | section unlocks report  | Course shortname > section number > instance id | Unlocks of a restriction on a section |
     *
     * @param string $type Identifies which type of page this is, e.g. 'activity unlocks report'.
     * @param string $identifier Identifies the particular page, e.g. 'PAGE1 > UNLOCK1'.
     * @return moodle_url The corresponding URL.
     * @throws Exception If the page type is not known or the identifier does not resolve.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'activity unlocks report':
                [$activity, $instanceid] = $this->explode_identifier($identifier, 2, $type);
                $cmid = $DB->get_field('course_modules', 'id', ['idnumber' => $activity], MUST_EXIST);

                return \availability_oncemet\local\unlocks::get_report_url((int) $cmid, 0, $instanceid);

            case 'section unlocks report':
                [$shortname, $section, $instanceid] = $this->explode_identifier($identifier, 3, $type);
                $courseid = $DB->get_field('course', 'id', ['shortname' => $shortname], MUST_EXIST);
                $sectionid = $DB->get_field('course_sections', 'id', [
                    'course' => $courseid,
                    'section' => (int) $section,
                ], MUST_EXIST);

                return \availability_oncemet\local\unlocks::get_report_url(0, (int) $sectionid, $instanceid);

            default:
                throw new Exception('Unrecognised availability_oncemet page type "' . $type . '."');
        }
    }

    /**
     * Splits a page identifier into its parts.
     *
     * @param string $identifier Identifier of the page, with its parts separated by '>'.
     * @param int $parts Number of parts which the identifier has to consist of.
     * @param string $type Page type, used for the error message only.
     * @return string[] The trimmed parts of the identifier.
     * @throws Exception If the identifier does not consist of the expected number of parts.
     */
    protected function explode_identifier(string $identifier, int $parts, string $type): array {
        $exploded = array_map('trim', explode('>', $identifier));

        if (count($exploded) !== $parts) {
            throw new Exception(
                'The identifier of an availability_oncemet "' . $type . '" page needs ' . $parts . ' parts, got: ' . $identifier
            );
        }

        return $exploded;
    }
}
