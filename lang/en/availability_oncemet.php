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
 * Availability OnceMet - Language pack.
 *
 * @package    availability_oncemet
 * @copyright  2026 Mahmoud Chehada, ssystems GmbH <mchehada@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addrestriction'] = 'Add restrictions that should be remembered once fulfilled.';
$string['confirmremove_continue'] = 'Remove restriction';
$string['confirmremove_message'] = 'Other users have already unlocked this Once met restriction permanently. Removing it deletes their permanent unlocks as soon as you save this form. Adding the very same restriction again afterwards does not bring the unlocks back, as a new restriction starts over: the affected users would have to fulfil the nested restrictions once more.';
$string['confirmremove_title'] = 'Remove the Once met restriction?';
$string['description'] = 'Once this condition has been met, keep it fulfilled permanently.';
$string['error_nochildren'] = 'Add at least one restriction.';
$string['error_notconfigured'] = 'This restriction is not configured correctly.';
$string['error_unknowninstance'] = 'This Once met restriction does not exist on this activity or course section.';
$string['error_unknownitem'] = 'The activity or course section of this Once met restriction was not given.';
$string['helptext_persistent'] = 'Users who met the nested restrictions once keep access even if those restrictions later change or no longer apply.';
$string['helptext_remove'] = 'Removing this Once met restriction from the activity or course section removes the permanent unlock. Any remaining restrictions continue to apply as usual.';
$string['oncemet:addinstance'] = 'Add Once met availability restrictions';
$string['oncemet:resetunlock'] = 'Reset the permanent unlocks of a Once met availability restriction';
$string['oncemet:viewunlocks'] = 'View the permanent unlocks of a Once met availability restriction';
$string['pluginname'] = 'Restriction by other restrictions met at least once';
$string['privacy:metadata:availability_oncemet'] = 'Information about the permanent unlocks which users have gained by fulfilling the nested restrictions of a Once met restriction.';
$string['privacy:metadata:availability_oncemet:availabilityuuid'] = 'The identifier of the Once met restriction which was fulfilled.';
$string['privacy:metadata:availability_oncemet:cmid'] = 'The ID of the activity which the Once met restriction was added to.';
$string['privacy:metadata:availability_oncemet:courseid'] = 'The ID of the course which contains the Once met restriction.';
$string['privacy:metadata:availability_oncemet:sectionid'] = 'The ID of the course section which the Once met restriction was added to.';
$string['privacy:metadata:availability_oncemet:timecreated'] = 'The time when the user fulfilled the Once met restriction.';
$string['privacy:metadata:availability_oncemet:userid'] = 'The ID of the user who fulfilled the Once met restriction.';
$string['requires_description'] = 'Met at least once: {$a}';
$string['requires_description_prefix'] = 'Met at least once:';
$string['requires_not_description'] = 'Not yet met at least once: {$a}';
$string['requires_not_description_prefix'] = 'Not yet met at least once:';
$string['title'] = 'Once met';
$string['uninstaller_remainingrestrictions'] = 'There are still {$a} activities or course sections which use a Once met restriction. These restrictions have not been removed, but they will be ignored from now on. Any restrictions which were nested inside them will be ignored as well, which means that the affected activities and course sections may have become available to everyone. Teachers have to remove these restrictions manually before they can save the restrictions of an affected activity or course section again.';
$string['unlocks_button'] = 'Review existing unlocks';
$string['unlocks_column_select'] = 'Select';
$string['unlocks_column_time'] = 'Unlocked at';
$string['unlocks_heading'] = 'Existing unlocks';
$string['unlocks_intro'] = 'This report lists the users who have permanently unlocked the following Once met restriction of "{$a}" by fulfilling its nested restrictions at least once:';
$string['unlocks_reset'] = 'Reset unlock';
$string['unlocks_resetdone'] = 'The permanent unlock was reset for {$a} users. They have to fulfil the nested restrictions again to regain access.';
