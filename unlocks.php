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
 * Availability OnceMet - Unlock report of a single Once met restriction.
 *
 * Which users hold a permanent unlock is otherwise invisible to the staff of a course, even though
 * it decides who gets past a Once met restriction. This page lists them and lets staff take an
 * unlock away again, and it is reached from the button which the availability form shows below the
 * explanation of the Once met restriction.
 *
 * @package    availability_oncemet
 * @copyright  2026 Alexander Bias, ssystems GmbH <abias@ssystems.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use availability_oncemet\condition;
use availability_oncemet\local\unlocks;
use core\output\checkbox_toggleall;
use core\output\html_writer;
use core\output\notification;
use core_availability\info_module;
use core_availability\info_section;
use core_table\flexible_table;

require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

$instanceid = required_param('instanceid', PARAM_ALPHANUMEXT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$sectionid = optional_param('sectionid', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

// Work out which activity or course section the restriction sits on. A Once met restriction always
// belongs to exactly one of the two, which is why exactly one of the two identifiers is expected.
$cm = null;
if ($cmid) {
    [$course, $cm] = get_course_and_cm_from_cmid($cmid);
    $context = context_module::instance($cm->id);
    $availability = $cm->availability;
} else if ($sectionid) {
    $sectionrecord = $DB->get_record('course_sections', ['id' => $sectionid], '*', MUST_EXIST);
    $course = get_course($sectionrecord->course);
    $context = context_course::instance($course->id);
    $availability = $sectionrecord->availability;
} else {
    throw new moodle_exception('error_unknownitem', 'availability_oncemet');
}

$pageurl = unlocks::get_report_url($cmid, $sectionid, $instanceid);
if ($returnurl !== '') {
    $pageurl->param('returnurl', $returnurl);
}
$PAGE->set_url($pageurl);

require_login($course, false, $cm);
require_capability('availability/oncemet:viewunlocks', $context);

// Only restrictions which really are on the item are reported on. Without this, the page would
// answer for any identifier which somebody cares to put into the URL, which is a way of asking
// whether a given user has ever met a restriction anywhere in the course. The structure of the
// restriction is kept, as an item can carry several of them and the report has to say which one of
// them it is about.
$instance = condition::get_instances($availability)[$instanceid] ?? null;
if ($instance === null) {
    throw new moodle_exception('error_unknowninstance', 'availability_oncemet');
}

// Whoever opened the report told it where to go back to. Without that, and with a URL which somebody
// typed by hand, the course page is the one place which is certainly worth returning to.
$backurl = $returnurl !== '' ? new moodle_url($returnurl) : course_get_url($course);

$canreset = has_capability('availability/oncemet:resetunlock', $context);

// Reset the unlocks of the selected users, if that is what this request is about. The report is
// shown again afterwards, so that the outcome can be seen right where the reset was asked for.
if (optional_param('reset', 0, PARAM_BOOL)) {
    require_sesskey();
    require_capability('availability/oncemet:resetunlock', $context);

    // An empty selection cannot arrive from the report itself, as its button stays disabled until a
    // user is ticked, so this is only reached by a hand-made request. Removing nothing is exactly
    // what such a request asks for, and the message below says as much.
    $removed = unlocks::reset($cmid, $sectionid, $instanceid, optional_param_array('unlockuserids', [], PARAM_INT));
    redirect(
        $pageurl,
        get_string('unlocks_resetdone', 'availability_oncemet', $removed),
        null,
        notification::NOTIFY_SUCCESS
    );
}

$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('unlocks_heading', 'availability_oncemet'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('unlocks_heading', 'availability_oncemet'), $pageurl);

// The name of the item and the description of the restriction are only worked out now. Formatting
// them needs the page context which require_login() has set, and neither of them is of any use on
// the request which resets an unlock and redirects straight away.
if ($cm !== null) {
    $itemname = format_string($cm->name);
    $info = new info_module($cm);
} else {
    $section = get_fast_modinfo($course)->get_section_info_by_id($sectionid, MUST_EXIST);
    $itemname = get_section_name($course, $sectionrecord);
    $info = new info_section($section);
}

// Which of the Once met restrictions of an item a report is about cannot be told from the item alone,
// and the instance id is a UUID which means nothing to a reader. The nested restrictions are what
// tells them apart, so the restriction is described here exactly as the availability form describes
// it. A NOT which the restriction may sit under is left out of that description on purpose: it says
// which restriction this is, not how the surrounding tree uses it.
try {
    $instancedescription = (new condition($instance))->get_description(true, false, $info);
} catch (coding_exception $e) {
    // A stored restriction which \availability_oncemet\condition refuses to build, because it lost
    // its nested restrictions or carries an identifier which does not fit the unlock table, cannot be
    // described either. That must not take the report down, though: its unlock records are still
    // there, and cleaning them up is exactly what somebody would come here for.
    $instancedescription = get_string('error_notconfigured', 'availability_oncemet');
}

// Set up the table. It is deliberately left without paging: an unlock report is a working list which
// staff filters by initial and then acts on as a whole, and a page which is submitted for the reset
// would only ever carry the users of the page which is on screen.
$table = new flexible_table('availability_oncemet_unlocks');
$table->define_baseurl($pageurl);
$table->set_attribute('id', 'availability-oncemet-unlocks');

$columns = [];
$headers = [];
if ($canreset) {
    $columns[] = 'select';
    $headers[] = get_string('unlocks_column_select', 'availability_oncemet');
}
$columns[] = 'firstname';
$headers[] = get_string('firstname');
$columns[] = 'lastname';
$headers[] = get_string('lastname');
$columns[] = 'timeunlocked';
$headers[] = get_string('unlocks_column_time', 'availability_oncemet');

$table->define_columns($columns);
$table->define_headers($headers);
$table->sortable(true, 'lastname', SORT_ASC);
if ($canreset) {
    $table->no_sorting('select');
}
$table->collapsible(false);
// This turns the initials preferences on, which are then read back below. The initials bar itself is
// printed by this page rather than by the table, as the table only prints one for a column which
// holds a whole user name and this report shows the first name and the last name separately.
$table->initialbars(true);
$table->setup();

// Filter by the initials which the user has picked, the way the participants page does it.
$where = [];
$whereparams = [];
if ($ifirst = $table->get_initial_first()) {
    $where[] = $DB->sql_like('u.firstname', ':ifirst', false, false);
    $whereparams['ifirst'] = $DB->sql_like_escape($ifirst) . '%';
}
if ($ilast = $table->get_initial_last()) {
    $where[] = $DB->sql_like('u.lastname', ':ilast', false, false);
    $whereparams['ilast'] = $DB->sql_like_escape($ilast) . '%';
}

$records = unlocks::get_records(
    $cmid,
    $sectionid,
    $instanceid,
    implode(' AND ', $where),
    $whereparams,
    $table->get_sql_sort()
);

// The bulk controls are of no use without rows to tick, and the form which carries them would submit
// an empty selection at best.
$showbulkactions = $canreset && !empty($records);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('unlocks_heading', 'availability_oncemet'));

// The sentence which introduces the report and the restriction which it is about are shown as one
// block, so that there is no doubt which of the Once met restrictions of the item the list below
// belongs to. The description carries the markup which core builds for a restriction, so it goes
// out as it is.
echo html_writer::div(
    html_writer::tag('p', get_string('unlocks_intro', 'availability_oncemet', $itemname)) . $instancedescription,
    'availability-oncemet-unlockrestriction mb-5 border rounded p-3'
);

echo $OUTPUT->initials_bar($table->get_initial_first(), 'firstinitial', get_string('firstname'), 'tifirst', $pageurl);
echo $OUTPUT->initials_bar($table->get_initial_last(), 'lastinitial', get_string('lastname'), 'tilast', $pageurl);

if ($showbulkactions) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $pageurl->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'reset', 'value' => 1]);
}

foreach ($records as $record) {
    $row = [];

    if ($canreset) {
        $row['select'] = $OUTPUT->render(new checkbox_toggleall('oncemet-unlocks', false, [
            'id' => 'unlockuser' . $record->userid,
            'name' => 'unlockuserids[]',
            'value' => $record->userid,
            // The label is rendered unescaped by the checkbox template, so the name goes in escaped.
            'label' => get_string('selectitem', 'moodle', s(fullname($record))),
            'labelclasses' => 'visually-hidden',
        ]));
    }

    // The user name fields are printed through the API which escapes them for output, and the time
    // of the unlock through the one which turns a timestamp into a date in the format and time zone
    // of the reader.
    $row['firstname'] = s($record->firstname);
    $row['lastname'] = s($record->lastname);
    $row['timeunlocked'] = userdate($record->timeunlocked);

    $table->add_data_keyed($row);
}

$table->finish_output();

if ($showbulkactions) {
    echo html_writer::start_div('availability-oncemet-unlockactions mt-3');
    echo $OUTPUT->render(new checkbox_toggleall('oncemet-unlocks', true, [
        'id' => 'oncemet-unlocks-selectall',
        'name' => 'oncemet-unlocks-selectall',
        'classes' => 'btn-link p-0 align-baseline',
    ], true));
    // Resetting without a selection would do nothing at all, so the button only becomes usable once
    // there is something to act on. The data attributes hand that over to core/checkbox-toggleall,
    // which is the very module that the checkboxes of this page already run on.
    echo html_writer::tag(
        'button',
        get_string('unlocks_reset', 'availability_oncemet'),
        [
            'type' => 'submit',
            'class' => 'btn btn-secondary ms-3',
            'disabled' => 'disabled',
            'data-action' => 'toggle',
            'data-toggle' => 'action',
            'data-togglegroup' => 'oncemet-unlocks',
        ]
    );
    echo html_writer::end_div();
    echo html_writer::end_tag('form');
}

echo html_writer::div(
    html_writer::link($backurl, get_string('back'), ['class' => 'btn btn-secondary']),
    'availability-oncemet-unlockback border-top mt-4 pt-3'
);

echo $OUTPUT->footer();
