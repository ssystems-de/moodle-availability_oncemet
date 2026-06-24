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
 * Availability OnceMet - Condition
 *
 * @package    availability_oncemet
 * @copyright  2026 Mahmoud Chehada, ssystems GmbH <mchehada@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_oncemet;

use availability_oncemet\local\description_messages;
use availability_oncemet\local\description_tree;
use core_availability\info;
use core_availability\info_module;
use core_availability\info_section;
use core_availability\tree;

/**
 * Availability OnceMet - Condition
 *
 * @package    availability_oncemet
 * @copyright  2026 Mahmoud Chehada, ssystems GmbH <mchehada@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /**
     * Unlocks held by a user within a course, as "userid_courseid" => unlock key => true.
     *
     * Deliberately a plain static cache which dies with the request. Unlock records are written by
     * this very class and are deleted as soon as a Once met restriction is removed from an activity
     * or course section, and that removal has to take effect at once: the form tells teachers that
     * removing the restriction removes the permanent unlock. Anything which outlives the request
     * would keep handing out access which was just revoked.
     *
     * Each entry holds the handful of unlocks which one user gained in one course, so an entry is
     * small. There can be a lot of them, though: the entries are keyed by user, and availability is
     * not only evaluated for the user a page renders for. Scheduled tasks walk through all enrolled
     * users of a course and would leave one entry per user behind, which is what
     * {@see self::UNLOCKS_CACHE_SIZE} is for.
     *
     * @var array
     */
    protected static array $unlocks = [];

    /**
     * Highest number of user and course combinations which {@see self::$unlocks} holds at once.
     *
     * Availability is evaluated for one user at a time, so one entry is what a page needs, and a
     * second one keeps a caller which alternates between two users from re-reading on every step.
     */
    protected const UNLOCKS_CACHE_SIZE = 2;

    /**
     * Highest number of characters which an instance identifier may consist of.
     *
     * Instance identifiers are written into the availabilityuuid column of the availability_oncemet
     * table, which is as wide as the UUID which the form produces. A longer identifier would either
     * be rejected by the database or, on a MySQL which does not run in strict mode, be truncated
     * silently, which would leave the stored identifier and the one of this condition disagreeing.
     */
    protected const INSTANCEID_MAX_LENGTH = 36;

    /**
     * @var tree Nested restriction tree.
     */
    protected tree $childtree;

    /**
     * @var string Stable identifier for this Once met instance on an activity or section.
     */
    protected string $instanceid;

    /**
     * Constructor.
     *
     * @param \stdClass $structure Data structure from JSON decode.
     * @throws \coding_exception If invalid data structure.
     */
    public function __construct($structure) {
        if (!isset($structure->c) || !is_object($structure->c)) {
            throw new \coding_exception('Missing or invalid ->c for oncemet condition');
        }
        // The nested restrictions are decoded leniently, exactly as core decodes the availability
        // tree which this condition is part of, see \core_availability\info::get_availability_tree().
        // A nested restriction whose plugin was disabled or uninstalled is dropped that way, while a
        // strict decode would throw out of this constructor. Core answers such an exception by
        // treating the whole item as unavailable without any reason text, which hides the item from
        // everybody, including the users who already hold an unlock for it.
        $this->childtree = new tree($structure->c, true, false);

        if (isset($structure->instanceid)) {
            // An availability field is written by everybody who may edit restrictions and can therefore
            // carry anything, while the instance id ends up in a fixed width column as soon as the first
            // unlock is stored. An id which does not fit that column is rejected here rather than there:
            // the insert would otherwise fail with a database exception which nobody catches, or store a
            // truncated id which no longer matches the one this condition asks for. Core conditions
            // answer a structure which they cannot work with in the very same way, see
            // \availability_date\condition, and \core_availability\info::is_available() turns that
            // exception into an item which is not available rather than into an error page.
            if (
                !is_string($structure->instanceid)
                || $structure->instanceid === ''
                || \core_text::strlen($structure->instanceid) > self::INSTANCEID_MAX_LENGTH
            ) {
                throw new \coding_exception('Invalid ->instanceid for oncemet condition');
            }

            $this->instanceid = $structure->instanceid;
        } else {
            // The form JavaScript always stores an instance id, so this is only reached for structures
            // which were built programmatically. Such an instance id is not persisted anywhere until
            // the condition is saved, which means that no unlock can outlive the current request.
            $this->instanceid = self::generate_instance_id();
        }
    }

    /**
     * Returns a JSON object which corresponds to a condition of this type.
     *
     * @param array $children Nested condition JSON objects.
     * @param string $op Operator (tree::OP_xx).
     * @param string|null $instanceid Stable instance identifier, generated if not given. It must not be
     *      longer than {@see self::INSTANCEID_MAX_LENGTH} characters, as the condition rejects it otherwise.
     * @return \stdClass Object representing condition.
     */
    public static function get_json(array $children, string $op = tree::OP_AND, ?string $instanceid = null): \stdClass {
        return (object) [
            'type' => 'oncemet',
            'instanceid' => $instanceid ?? self::generate_instance_id(),
            'c' => tree::get_nested_json($children, $op),
        ];
    }

    /**
     * Saves tree data back to a structure object.
     *
     * @return \stdClass Structure object representing the condition.
     */
    public function save(): \stdClass {
        return (object) [
            'type' => 'oncemet',
            'instanceid' => $this->instanceid,
            'c' => $this->childtree->save(),
        ];
    }

    /**
     * Determines whether a particular item is currently available.
     *
     * @param bool $not Set true if we are inverting the condition.
     * @param info $info Item we're checking.
     * @param bool $grabthelot Performance hint: if true, caches information required for all course-modules.
     * @param int $userid User ID to check availability for.
     * @return bool True if available.
     */
    public function is_available($not, info $info, $grabthelot, $userid) {
        // A stored unlock states that the nested restrictions were met at some point, which is what
        // this condition is about. A NOT which is in effect is applied to that statement as usual.
        if ($this->has_unlock($userid, $info)) {
            return !$not;
        }

        // A restriction without nested restrictions is not configured and must never grant access.
        // The NOT is deliberately not applied to this: handing the plain result up would turn an
        // unconfigured restriction into one which opens the item for everybody within an inverted
        // tree. Users who gained their unlock while the restriction still had nested restrictions
        // keep it, which is why this sits behind the check above.
        // However, having said this, this case should never happen in a healthy setup as the GUI
        // prevents the creation of such rules. This is just a security net.
        if ($this->childtree->is_empty()) {
            return false;
        }

        // Evaluate the contained restrictions.
        $met = $this->evaluate_children($info, $grabthelot, $userid);

        // The unlock holds the very same statement as the branch above, so it is stored whenever the
        // nested restrictions are met, no matter whether a NOT is in effect. The NOT is applied to
        // that statement afterwards, exactly as it is applied to a stored unlock.
        if ($met) {
            $this->store_unlock($userid, $info);
        }

        // Return.
        return $not ? !$met : $met;
    }

    /**
     * Determines whether this condition is applied to user lists.
     *
     * It is not, and this override just returns the inherited default. It exists for its
     * documentation only, as the opposite conclusion is tempting and would be wrong: an unlock is
     * permanent by design, and the rule in
     * {@see \core_availability\tree_node::is_applied_to_user_lists()} asks exactly for permanence
     * - conditions which are likely to be permanent (group, grouping, profile) filter user lists,
     * conditions which are likely to be temporary (date, grade, completion) do not.
     *
     * Permanence is only half of the picture though. What a Once met restriction states is decided
     * by the restrictions which are nested within it, and these are arbitrary. A "Once met (Date)"
     * restriction wraps a temporary condition, so the plugin cannot claim permanence in general.
     * Delegating the decision to the nested tree is no way out either, as
     * \core_availability\tree::is_applied_to_user_lists() returns true unconditionally and
     * therefore does not distinguish anything.
     *
     * On top of that, joining user list filtering would mean implementing filter_user_list() with
     * correct $not handling and a get_user_list_sql() which unions the unlock table with the SQL of
     * the nested restrictions. A mistake in there would silently remove people from participant and
     * override lists - which are exactly the lists where a teacher looks for the learners who have
     * not made it yet.
     *
     * @return bool Always false as this condition does not filter user lists.
     */
    public function is_applied_to_user_lists() {
        return false;
    }

    /**
     * Checks whether an unlock record exists for this user and Once met instance.
     *
     * @param int $userid User id.
     * @param info $info Availability info object.
     * @return bool
     */
    public function has_unlock(int $userid, info $info): bool {
        if (!$userid) {
            return false;
        }

        [$courseid, $cmid, $sectionid] = $this->get_context_ids($info);

        $unlocks = self::get_unlocks($userid, $courseid);

        return isset($unlocks[self::unlock_key($cmid, $sectionid, $this->instanceid)]);
    }

    /**
     * Returns all unlocks which a user holds within a course.
     *
     * A course page asks this condition about one Once met instance after the other, and every
     * single one of these questions used to be a database query of its own. The whole set is read
     * at once instead, which is cheap: the number of unlocks a user holds within a course is bound
     * by the number of Once met restrictions which that course uses. The $grabthelot hint of
     * is_available() is therefore not needed to decide whether reading ahead is worth it.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @return array Unlock keys of the user, as key => true.
     */
    protected static function get_unlocks(int $userid, int $courseid): array {
        global $DB;

        $cachekey = $userid . '_' . $courseid;

        if (!isset(self::$unlocks[$cachekey])) {
            $unlocks = [];

            $records = $DB->get_records(
                'availability_oncemet',
                ['userid' => $userid, 'courseid' => $courseid],
                '',
                'id, cmid, sectionid, availabilityuuid'
            );
            foreach ($records as $record) {
                $unlocks[self::unlock_key($record->cmid, $record->sectionid, $record->availabilityuuid)] = true;
            }

            // Make room for the new entry. Dropping the oldest one costs nothing but the query which
            // reads it again, as an entry which is gone is an entry which is simply asked for anew.
            while (count(self::$unlocks) >= self::UNLOCKS_CACHE_SIZE) {
                array_shift(self::$unlocks);
            }

            self::$unlocks[$cachekey] = $unlocks;
        }

        return self::$unlocks[$cachekey];
    }

    /**
     * Builds the key which identifies one unlock within the unlocks of a user and course.
     *
     * @param int $cmid Course module id, 0 if the unlock does not belong to an activity.
     * @param int $sectionid Course section id, 0 if the unlock does not belong to a course section.
     * @param string $instanceid Once met instance id.
     * @return string
     */
    protected static function unlock_key(int $cmid, int $sectionid, string $instanceid): string {
        return $cmid . '_' . $sectionid . '_' . $instanceid;
    }

    /**
     * Adds a freshly stored unlock to the cache.
     *
     * @param int $userid User id.
     * @param int $courseid Course id.
     * @param int $cmid Course module id, 0 if the unlock does not belong to an activity.
     * @param int $sectionid Course section id, 0 if the unlock does not belong to a course section.
     * @param string $instanceid Once met instance id.
     */
    protected static function remember_unlock(
        int $userid,
        int $courseid,
        int $cmid,
        int $sectionid,
        string $instanceid
    ): void {
        $cachekey = $userid . '_' . $courseid;

        if (isset(self::$unlocks[$cachekey])) {
            self::$unlocks[$cachekey][self::unlock_key($cmid, $sectionid, $instanceid)] = true;
        }
    }

    /**
     * Wipes the static cache used to store the unlocks of a user.
     *
     * This has to be called whenever unlock records are deleted, see \availability_oncemet\observer
     * and \availability_oncemet\privacy\provider.
     */
    public static function wipe_static_cache(): void {
        self::$unlocks = [];
    }

    /**
     * Stores an unlock record for the user.
     *
     * @param int $userid User id.
     * @param info $info Availability info object.
     */
    public function store_unlock(int $userid, info $info): void {
        global $DB;

        if (!$this->can_store_unlock($userid)) {
            return;
        }

        if ($this->has_unlock($userid, $info)) {
            return;
        }

        [$courseid, $cmid, $sectionid] = $this->get_context_ids($info);

        $record = (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'sectionid' => $sectionid,
            'availabilityuuid' => $this->instanceid,
            'timecreated' => time(),
        ];

        try {
            $DB->insert_record('availability_oncemet', $record);
        } catch (\dml_write_exception $e) {
            // A parallel request has stored the very same unlock between our check and this insert.
            // The unique index rejects the duplicate, which is exactly the outcome we want anyway.
            // Swallowing it is safe even while a transaction is open: the PostgreSQL driver keeps a
            // savepoint of its own for exactly this and rolls back to it whenever a statement fails,
            // see the query_end() of \pgsql_native_moodle_database and MDL-35506, and the other
            // supported databases do not abort a transaction over a rejected statement either.
            // The cache was read before that request wrote its record, so it has to be dropped
            // before asking again, as it would otherwise just repeat its outdated answer.
            self::wipe_static_cache();
            if (!$this->has_unlock($userid, $info)) {
                throw $e;
            }
            return;
        }

        self::remember_unlock($userid, $courseid, $cmid, $sectionid, $this->instanceid);
    }

    /**
     * Whether an unlock record may be stored for the user.
     *
     * @param int $userid User id.
     * @return bool
     */
    public function can_store_unlock(int $userid): bool {
        return $userid > 0 && !isguestuser($userid);
    }

    /**
     * Returns the stable identifier for this Once met instance.
     *
     * Unlock records are tied to the instance, not to the nested configuration. That is what allows
     * teachers to edit the nested restrictions without revoking the unlocks which users already gained.
     *
     * @return string
     */
    public function get_instance_id(): string {
        return $this->instanceid;
    }

    /**
     * Generates a new stable instance identifier.
     *
     * @return string
     */
    public static function generate_instance_id(): string {
        return \core\uuid::generate();
    }

    /**
     * Extracts the identifiers of all Once met instances within a raw availability field.
     *
     * This is used to find the unlock records which do not belong to any Once met restriction anymore.
     *
     * @param string|null $availability Raw availability JSON of an activity or course section.
     * @return string[] Instance identifiers, may be empty.
     */
    public static function get_instance_ids(?string $availability): array {
        return array_keys(self::get_instances($availability));
    }

    /**
     * Extracts all Once met instances within a raw availability field.
     *
     * The structures are handed back rather than just their identifiers wherever one Once met
     * restriction has to be told apart from the others of the same item, which is what the unlock
     * report needs: an item can carry several of them, and only the nested restrictions say which
     * one of them a report is about.
     *
     * @param string|null $availability Raw availability JSON of an activity or course section.
     * @return \stdClass[] Once met condition structures, keyed by their instance identifier.
     */
    public static function get_instances(?string $availability): array {
        if (empty($availability)) {
            return [];
        }

        $structure = json_decode($availability);
        if (!is_object($structure)) {
            return [];
        }

        return self::collect_instances($structure);
    }

    /**
     * Recursively collects the Once met instances of an availability tree.
     *
     * @param \stdClass $tree Availability tree structure.
     * @return \stdClass[] Once met condition structures, keyed by their instance identifier.
     */
    protected static function collect_instances(\stdClass $tree): array {
        if (!isset($tree->c) || !is_array($tree->c)) {
            return [];
        }

        $instances = [];

        foreach ($tree->c as $child) {
            if (!is_object($child)) {
                continue;
            }

            // Any child without a type is a nested group of restrictions.
            if (!isset($child->type)) {
                $instances += self::collect_instances($child);
                continue;
            }

            // Any child with another type is a restriction of another availability plugin.
            if ($child->type !== 'oncemet') {
                continue;
            }

            if (!empty($child->instanceid) && is_string($child->instanceid)) {
                $instances[$child->instanceid] = $child;
            }

            // A Once met restriction can be nested within another Once met restriction.
            if (isset($child->c) && is_object($child->c)) {
                $instances += self::collect_instances($child->c);
            }
        }

        return $instances;
    }

    /**
     * Evaluates nested child restrictions.
     *
     * @param info $info Availability info object.
     * @param bool $grabthelot Performance hint.
     * @param int $userid User id.
     * @return bool
     */
    public function evaluate_children(info $info, bool $grabthelot, int $userid): bool {
        if ($this->childtree->is_empty()) {
            return false;
        }

        $result = $this->childtree->check_available(false, $info, $grabthelot, $userid);
        return $result->is_available();
    }

    /**
     * Obtains a string describing this restriction (whether or not it actually applies).
     *
     * @param bool $full Set true if this is the 'full information' view.
     * @param bool $not Set true if we are inverting the condition.
     * @param info $info Item we're checking.
     * @return string|\core_availability_multiple_messages Information string (for admin) about all restrictions.
     */
    public function get_description($full, $not, info $info) {
        // Learners are not meant to be aware of the Once met mechanism. They are shown the nested
        // restrictions as if these had been added to the activity or section directly, which is why
        // a NOT which is in effect has to be handed down to the nested restrictions here.
        if (!$full) {
            return $this->get_nested_description($not, $info, false);
        }

        // Staff sees the nested restrictions wrapped into a Once met label. That label already
        // expresses a NOT in words, so the nested restrictions are described as they are configured.
        return $this->get_labelled_description($this->get_nested_description(false, $info, false), $not);
    }

    /**
     * Obtains a representation of the options of this condition as a string for display.
     *
     * @param bool $full Set true if this is the 'full information' view.
     * @param bool $not Set true if we are inverting the condition.
     * @param info $info Item we're checking.
     * @return string|\core_availability_multiple_messages Information string (for admin) about all restrictions.
     */
    public function get_standalone_description($full, $not, info $info) {
        // As in get_description(), learners are shown the nested restrictions as if these were the
        // restrictions of the activity or section themselves.
        if (!$full) {
            return $this->get_nested_description($not, $info, true);
        }

        $nested = $this->get_nested_description(false, $info, false);
        $description = $this->get_labelled_description($nested, $not);

        // A description which carries a list of nested restrictions becomes the only item of the root
        // list, so that core renders that list indented below the Once met label. A description of a
        // single nested restriction is plain text and is prefixed as core does it for any condition.
        if ($nested instanceof \core_availability_multiple_messages) {
            return new description_messages(true, true, false, [$description]);
        }

        return get_string('list_root_and', 'availability') . ' ' . $description;
    }

    /**
     * Builds the description which tells staff that a Once met restriction is in play.
     *
     * @param string|\core_availability_multiple_messages $nested Description of the nested restrictions.
     * @param bool $not Whether the condition is inverted.
     * @return string
     */
    protected function get_labelled_description($nested, bool $not): string {
        if ($nested instanceof \core_availability_multiple_messages) {
            return $this->wrap_multiple_description($nested, $not);
        }

        if ($not) {
            return get_string('requires_not_description', 'availability_oncemet', $nested);
        }

        return get_string('requires_description', 'availability_oncemet', $nested);
    }

    /**
     * Obtains a string describing this restriction, used for debugging.
     *
     * @return string Text representation of parameters.
     */
    protected function get_debug_string() {
        return $this->get_instance_id();
    }

    /**
     * Updates this node after restore, returning true if anything changed.
     *
     * @param string $table Table name, e.g. 'course_modules'.
     * @param int $oldid Previous ID.
     * @param int $newid New ID.
     * @return bool True if it changed.
     */
    public function update_dependency_id($table, $oldid, $newid) {
        return $this->childtree->update_dependency_id($table, $oldid, $newid);
    }

    /**
     * Updates this node after restore, returning true if anything changed.
     *
     * @param string $restoreid Restore ID.
     * @param int $courseid Course ID.
     * @param \base_logger $logger Logger for messages.
     * @param string $name Name of this item (for use in messages).
     * @return bool True if there was any change.
     */
    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name) {
        return $this->childtree->update_after_restore($restoreid, $courseid, $logger, $name);
    }

    /**
     * Checks whether this condition should be included after restore.
     *
     * @param string $restoreid Restore ID.
     * @param int $courseid Course ID.
     * @param \base_logger $logger Logger for messages.
     * @param string $name Name of this item (for use in messages).
     * @param \base_task $task Restore task.
     * @return bool True if there was any change.
     */
    public function include_after_restore($restoreid, $courseid, \base_logger $logger, $name, \base_task $task) {
        return $this->childtree->include_after_restore($restoreid, $courseid, $logger, $name, $task);
    }

    /**
     * Gets course, cm and section identifiers for unlock storage.
     *
     * @param info $info Availability info object.
     * @return array Course id, cm id (or null), section id (or null).
     */
    protected function get_context_ids(info $info): array {
        $courseid = $info->get_course()->id;
        $cmid = 0;
        $sectionid = 0;

        if ($info instanceof info_module) {
            $cmid = $info->get_course_module()->id;
        } else if ($info instanceof info_section) {
            $sectionid = $info->get_section()->id;
        }

        return [$courseid, $cmid, $sectionid];
    }

    /**
     * Builds a human-readable description of nested restrictions.
     *
     * @param bool $not Whether a NOT is in effect for the nested restrictions.
     * @param info $info Availability info object.
     * @param bool $root Whether the nested restrictions are the only restrictions of the item.
     * @return string|\core_availability_multiple_messages
     */
    protected function get_nested_description(bool $not, info $info, bool $root) {
        // A restriction without nested restrictions cannot be described. The string which the form
        // shows in this case asks the teacher to add one, which would be an odd thing to read on a
        // course page, so this states the problem instead of asking the reader to solve it.
        if ($this->childtree->is_empty()) {
            return get_string('error_notconfigured', 'availability_oncemet');
        }

        $nestedtree = new description_tree($this->childtree->save(), true, false);

        return $nestedtree->describe($not, $info, $root);
    }

    /**
     * Wraps a nested multiple-message description with the Once met label.
     *
     * Core renders every entry of a restriction list as a bullet of one and the same list. Handing
     * the label and the nested restrictions over as two entries would therefore put them next to
     * each other instead of below each other. The nested restrictions are rendered to HTML here and
     * are returned together with the label as one single entry, which makes core nest them within
     * the list item of the label.
     *
     * @param \core_availability_multiple_messages $nested Nested restriction description.
     * @param bool $not Whether the condition is inverted.
     * @return string
     */
    protected function wrap_multiple_description(\core_availability_multiple_messages $nested, bool $not): string {
        global $OUTPUT;

        if ($not) {
            $label = get_string('requires_not_description_prefix', 'availability_oncemet');
        } else {
            $label = get_string('requires_description_prefix', 'availability_oncemet');
        }

        // The rendered nested restrictions start with their own list header, which would end up right
        // next to the label. They are therefore put into a list of their own, exactly as core does it
        // in the core_availability/availability_info template. The additional class allows the plugin
        // stylesheet to keep them out of the collapsed availability excerpt.
        $rendered = $OUTPUT->render(new \core_availability\output\availability_info($nested));
        $list = \core\output\html_writer::tag(
            'ul',
            \core\output\html_writer::tag('li', $rendered),
            ['class' => 'availability-oncemet-restrictions', 'data-region' => 'availability-multiple']
        );

        return $label . $list;
    }
}
