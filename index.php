<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Lists all quickpoll instances in the requested course.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_course_login($course);

$context = context_course::instance($course->id);
require_capability('mod/quickpoll:viewresults', $context);

$PAGE->set_url('/mod/quickpoll/index.php', ['id' => $courseid]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->navbar->add(get_string('modulenameplural', 'mod_quickpoll'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_quickpoll'));

$polls = quickpoll_get_all_instances_in_course($courseid);

if (empty($polls)) {
    notice(get_string('noanswersyet', 'mod_quickpoll'), new moodle_url('/course/view.php', ['id' => $courseid]));
}

$table             = new html_table();
$table->attributes = ['class' => 'generaltable mod_index'];
$table->head       = [
    get_string('name'),
    get_string('timeopen', 'mod_quickpoll'),
    get_string('timeclose', 'mod_quickpoll'),
];
$table->align = ['left', 'center', 'center'];

$modinfo  = get_fast_modinfo($course);
$sections = $modinfo->get_sections();

foreach ($polls as $poll) {
    $cm = $modinfo->get_cm($poll->coursemodule);

    if (!$cm->uservisible) {
        continue;
    }

    $link = html_writer::link(
        new moodle_url('/mod/quickpoll/view.php', ['id' => $poll->coursemodule]),
        format_string($poll->name, true)
    );

    $timeopen  = $poll->timeopen ? userdate($poll->timeopen) : get_string('openingon', 'mod_quickpoll', '-');
    $timeclose = $poll->timeclose ? userdate($poll->timeclose) : get_string('closingon', 'mod_quickpoll', '-');

    $table->data[] = [$link, $timeopen, $timeclose];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
