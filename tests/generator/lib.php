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
 * Availability OnceMet - Data generator.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Availability OnceMet - Data generator.
 *
 * Restricts activities and course sections by a Once met restriction and stores the unlocks which
 * users have supposedly earned from them, without going through the availability form. This is what
 * the Behat generator of the plugin builds on, see {@see \behat_availability_oncemet_generator} for
 * how the entities are named in a feature file.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class availability_oncemet_generator extends component_generator_base {
    /**
     * Root operators which carry one display setting per condition instead of one for the whole
     * tree. See \core_availability\tree::save() for where this distinction comes from.
     */
    const SHOWC_OPERATORS = ['&', '!|'];

    /**
     * Readable aliases for the root operators.
     *
     * A raw '|' has to be escaped in a Gherkin table, and an escaped one does not survive every
     * table formatter, so feature files spell the operators out instead.
     */
    const ROOT_OPERATORS = [
        'and' => '&',
        'or' => '|',
        'not and' => '!&',
        'not or' => '!|',
    ];

    /**
     * Restricts an activity by a Once met restriction.
     *
     * Calling this repeatedly for the same activity appends another Once met restriction to the
     * ones which are already there, which is how an item with more than one instance is built.
     *
     * @param array $data Restriction data, see {@see self::build_availability()} for the options.
     *                    Needs a 'cmid'.
     */
    public function create_activity_restriction(array $data): void {
        global $DB;

        $cmid = (int) $data['cmid'];
        $courseid = $DB->get_field('course_modules', 'course', ['id' => $cmid], MUST_EXIST);
        $current = $DB->get_field('course_modules', 'availability', ['id' => $cmid], MUST_EXIST);

        $DB->set_field('course_modules', 'availability', $this->build_availability($current, $data), ['id' => $cmid]);

        // The availability of an item is part of the course cache, which was built without it.
        rebuild_course_cache($courseid, true);
    }

    /**
     * Restricts a course section by a Once met restriction.
     *
     * @param array $data Restriction data, see {@see self::build_availability()} for the options.
     *                    Needs a 'courseid' and a 'section'.
     */
    public function create_section_restriction(array $data): void {
        global $DB;

        $courseid = (int) $data['courseid'];
        $params = ['course' => $courseid, 'section' => (int) $data['section']];
        $current = $DB->get_field('course_sections', 'availability', $params, MUST_EXIST);

        $DB->set_field('course_sections', 'availability', $this->build_availability($current, $data), $params);

        rebuild_course_cache($courseid, true);
    }

    /**
     * Stores an unlock record, as if the user had fulfilled the nested restrictions of a Once met
     * restriction at some point in the past.
     *
     * This writes the very record which the condition writes itself, which lets a scenario start
     * from a user who already holds the unlock instead of first earning and then losing it. Whether
     * viewing an item actually writes that record is a question of its own and is answered by the
     * one scenario of availability_oncemet_restrict.feature which does earn an unlock through the
     * user interface.
     *
     * @param array $data Unlock data. Needs a 'userid' and an 'instanceid', plus either a 'cmid'
     *                    for an activity or a 'courseid' and a 'section' for a course section.
     * @throws coding_exception If the unlock names neither an activity nor a course section.
     */
    public function create_unlock(array $data): void {
        global $DB;

        $cmid = 0;
        $sectionid = 0;

        if (!empty($data['cmid'])) {
            $cmid = (int) $data['cmid'];
            $courseid = (int) $DB->get_field('course_modules', 'course', ['id' => $cmid], MUST_EXIST);
        } else if (isset($data['section']) && !empty($data['courseid'])) {
            $courseid = (int) $data['courseid'];
            $sectionid = (int) $DB->get_field('course_sections', 'id', [
                'course' => $courseid,
                'section' => (int) $data['section'],
            ], MUST_EXIST);
        } else {
            throw new coding_exception('An unlock needs either an "activity" or a "course" and a "section".');
        }

        $DB->insert_record('availability_oncemet', (object) [
            'userid' => (int) $data['userid'],
            'courseid' => $courseid,
            'cmid' => $cmid,
            'sectionid' => $sectionid,
            'availabilityuuid' => $data['instanceid'],
            'timecreated' => time(),
        ]);
    }

    /**
     * Adds a Once met condition to the availability tree of an item.
     *
     * The condition is appended to whatever the item carries already instead of replacing it, so
     * that calling this twice for one item yields the two Once met blocks which a teacher would get
     * by adding the restriction twice in the form. The result is written the way
     * \core_availability\tree::save() would write it, because that is what the form stores and what
     * the condition classes expect to read back.
     *
     * @param string|null $current Availability field of the item as it is now, empty for an
     *                             unrestricted item.
     * @param array $data Restriction data. 'instanceid' is the stable identifier which unlock
     *                    records are tied to. The restriction tree to nest inside comes either
     *                    from 'nested' as JSON, see {@see self::resolve_placeholders()} for
     *                    referring to activities from within it, or from the 'profile' shorthand,
     *                    see {@see self::build_profile_tree()}. 'rootop' is the operator of the root tree, one
     *                    of 'and' (the default), 'or', 'not and' and 'not or', or the symbol which
     *                    the JSON uses for it. 'hidden' hides the item
     *                    entirely instead of showing it greyed out with its reason, the way the
     *                    closed eye does.
     * @return string JSON for the availability field.
     * @throws coding_exception If the nested restriction tree is not valid JSON.
     */
    protected function build_availability(?string $current, array $data): string {
        // The nested tree is handed over as raw JSON, so a typo in a feature file would otherwise
        // end up in the database and only surface much later as an unreadable availability field.
        $json = $this->resolve_nested($data);
        $nested = json_decode($this->resolve_placeholders($json));
        if (!is_object($nested)) {
            throw new coding_exception('The "nested" field does not hold a valid restriction tree: ' . $json);
        }

        // These three keys are exactly what \availability_oncemet\condition::save() produces.
        $condition = (object) [
            'type' => 'oncemet',
            'instanceid' => $data['instanceid'],
            'c' => $nested,
        ];

        // The operator may be given as a word or as the symbol which ends up in the JSON.
        $rootop = !empty($data['rootop']) ? trim($data['rootop']) : 'and';
        $rootop = self::ROOT_OPERATORS[strtolower($rootop)] ?? $rootop;
        if (!in_array($rootop, self::ROOT_OPERATORS, true)) {
            throw new coding_exception('The "rootop" field is not a known operator: ' . $data['rootop']);
        }

        $hidden = !empty($data['hidden']);

        // Start from what the item carries already, so that a second call appends rather than
        // overwrites. Anything which is not a usable root tree is treated as an unrestricted item,
        // which covers both an empty availability field and the null of a fresh activity.
        $tree = json_decode((string) $current);
        if (!is_object($tree) || !isset($tree->c) || !is_array($tree->c)) {
            $tree = (object) ['op' => $rootop, 'c' => []];
        }

        // Collect how each condition which is already there is displayed, so that appending a
        // second Once met restriction does not silently change the display of the first one. The
        // settings may arrive as a list or as a single value, depending on which operator the tree
        // had so far, and the padding covers a tree which carried neither.
        if (isset($tree->showc) && is_array($tree->showc)) {
            $show = $tree->showc;
        } else {
            $show = array_fill(0, count($tree->c), isset($tree->show) ? (bool) $tree->show : true);
        }
        $show = array_pad($show, count($tree->c), true);

        // Append the new condition and its own display setting. The operator of the row wins, as a
        // tree has only one, and rows for the same item are expected to agree on it.
        $tree->op = $rootop;
        $tree->c[] = $condition;
        $show[] = !$hidden;

        // Whether a root tree carries one display setting per condition or a single one for all of
        // them depends on its operator, not on whether it is negated. Both keys are dropped first,
        // because switching the operator switches which of the two is the valid one and leaving the
        // other behind would make \core_availability\tree reject the structure.
        unset($tree->show, $tree->showc);
        if (in_array($rootop, self::SHOWC_OPERATORS, true)) {
            $tree->showc = $show;
        } else {
            // These operators carry one setting for the whole tree, so anything which is meant to
            // be hidden hides all of it.
            $tree->show = !in_array(false, $show, true);
        }

        return json_encode($tree);
    }

    /**
     * Works out the nested restriction tree of a restriction.
     *
     * It is either written out as JSON in 'nested' or described by the 'profile' shorthand, and
     * exactly one of the two has to be given. Everything which is not a plain comparison of user
     * profile fields needs the JSON, so the shorthand never replaces it, it only keeps the common
     * case out of the feature tables.
     *
     * @param array $data Restriction data.
     * @return string JSON of the nested restriction tree.
     * @throws coding_exception If neither or both of the two are given.
     */
    protected function resolve_nested(array $data): string {
        $hasnested = isset($data['nested']) && trim($data['nested']) !== '';
        $hasprofile = isset($data['profile']) && trim($data['profile']) !== '';

        if ($hasnested === $hasprofile) {
            throw new coding_exception('A restriction needs either a "nested" tree or a "profile" shorthand.');
        }

        return $hasnested ? $data['nested'] : $this->build_profile_tree($data['profile']);
    }

    /**
     * Builds a restriction tree of user profile comparisons from the shorthand notation.
     *
     * The shorthand is a comma separated list of "field = value" pairs, which become one profile
     * condition each, all of which have to be met:
     *
     *     city = Nowhere, department = Sales
     *
     * An email address works just as well as any other field, it is only kept out of this example
     * because the PHPDoc checker reads the "@" of an address as the start of an inline tag.
     *
     * The field is the shortname of a standard user profile field and the comparison is always on
     * equality, which is what the restrictions of these tests are about. Anything else, such as
     * another operator or a custom profile field, has to be written out as JSON in 'nested'.
     *
     * @param string $shorthand Comma separated list of comparisons.
     * @return string JSON of the nested restriction tree.
     * @throws coding_exception If a comparison is not a "field = value" pair.
     */
    protected function build_profile_tree(string $shorthand): string {
        $conditions = [];

        foreach (explode(',', $shorthand) as $comparison) {
            $parts = explode('=', $comparison, 2);
            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                throw new coding_exception('The "profile" shorthand needs "field = value" pairs, got: ' . $comparison);
            }

            $conditions[] = [
                'type' => 'profile',
                'op' => 'isequalto',
                'sf' => trim($parts[0]),
                'v' => trim($parts[1]),
            ];
        }

        return json_encode(['op' => '&', 'c' => $conditions]);
    }

    /**
     * Replaces activity references within a nested restriction tree by the course module id.
     *
     * A nested restriction which points at another activity, such as the completion condition,
     * stores a course module id as a number. That id does not exist yet while a feature file is
     * being written, so the tree names the activity by its idnumber instead:
     *
     *     {"op":"&","c":[{"type":"completion","cm":"##cmid:PAGE1##","e":1}]}
     *
     * The quotes around the placeholder are part of what is replaced, so that the result is the
     * bare number which the condition expects.
     *
     * @param string $json Nested restriction tree, possibly holding placeholders.
     * @return string The same tree with all placeholders resolved.
     */
    protected function resolve_placeholders(string $json): string {
        return preg_replace_callback('/"##cmid:([^#"]+)##"/', function (array $matches): string {
            global $DB;

            if (!$cmid = $DB->get_field('course_modules', 'id', ['idnumber' => $matches[1]])) {
                throw new coding_exception('The activity with idnumber "' . $matches[1] . '" could not be found.');
            }

            return (string) $cmid;
        }, $json);
    }
}
