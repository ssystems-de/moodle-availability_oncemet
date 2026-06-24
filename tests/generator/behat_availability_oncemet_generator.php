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
 * Availability OnceMet - Behat data generator.
 *
 * Restricts activities and course sections by a Once met restriction, without going through the
 * form, and stores the unlocks which users have supposedly earned from it:
 *
 *     Given the following "availability_oncemet > activity restrictions" exist:
 *       | activity | instanceid | nested                                                   | rootop | hidden |
 *       | PAGE1    | UNLOCK1    | {"op":"&","c":[{"type":"date","d":">=","t":1893452400}]} | and    |        |
 *
 *     Given the following "availability_oncemet > activity restrictions" exist:
 *       | activity | instanceid | profile                          |
 *       | PAGE1    | UNLOCK1    | city = Split, department = Sales  |
 *
 *     Given the following "availability_oncemet > section restrictions" exist:
 *       | course | section | instanceid | nested |
 *       | C1     | 1       | UNLOCK1    | ...    |
 *
 *     Given the following "availability_oncemet > unlocks" exist:
 *       | user     | activity | instanceid |
 *       | learner1 | PAGE1    | UNLOCK1    |
 *
 *     Given the following "availability_oncemet > unlocks" exist:
 *       | user     | course | section | instanceid |
 *       | learner1 | C1     | 1       | UNLOCK1    |
 *
 * The activity is named by its idnumber, the course by its shortname and the user by their
 * username. "instanceid" is required everywhere rather than generated, because it is what ties an
 * unlock to a restriction: a generated one could not be named in the unlock table, and a mismatch
 * between the two would show up as a scenario which fails for no visible reason. It is stored in a
 * column of 36 characters, so it has to stay at most that long.
 *
 * "nested" holds the restriction tree to wrap, written the way the form stores it. To point at an
 * activity from within it, for instance from a completion condition, use "##cmid:IDNUMBER##" in
 * place of the course module id.
 *
 * "profile" is a shorthand for the common case of comparing user profile fields, given as a comma
 * separated list of "field = value" pairs which all have to be met. It saves spelling out the
 * whole tree and is entirely optional: every restriction can still be written out under "nested",
 * and anything beyond plain equality of standard profile fields has to be. Give one of the two,
 * never both.
 *
 * "rootop" is the operator of the root tree, spelled out as "and"
 * (the default), "or", "not and" or "not or" rather than as the symbol, because a raw "|" would
 * have to be escaped in a Gherkin table and an escaped one does not survive every table formatter.
 * Use "not and" for a restriction which is turned around the way the "must not" option of the form
 * does it, and "or" to put several Once met restrictions on one item side by side. "hidden" hides
 * the item entirely instead of showing it greyed out with its reason, the way the closed eye does.
 *
 * The availability form is written in YUI, so configuring a restriction through the user interface
 * needs a JavaScript scenario and a good handful of steps. That is worth doing where the form
 * itself is the subject, see availability_oncemet_form.feature. Every other feature only needs the
 * restriction to be in place, and gets it from here instead.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_availability_oncemet_generator extends behat_generator_base {
    /**
     * Get a list of the entities that Behat can create using the generator step.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'activity restrictions' => [
                'singular' => 'activity restriction',
                'datagenerator' => 'activity_restriction',
                // The nested tree is required too, but it may arrive as "nested" or as "profile",
                // which the data generator checks because only one of the two has to be there.
                'required' => ['activity', 'instanceid'],
                'switchids' => ['activity' => 'cmid'],
            ],
            'section restrictions' => [
                'singular' => 'section restriction',
                'datagenerator' => 'section_restriction',
                'required' => ['course', 'section', 'instanceid'],
                'switchids' => ['course' => 'courseid'],
            ],
            'unlocks' => [
                'singular' => 'unlock',
                'datagenerator' => 'unlock',
                'required' => ['user', 'instanceid'],
                'switchids' => ['user' => 'userid', 'activity' => 'cmid', 'course' => 'courseid'],
            ],
        ];
    }
}
