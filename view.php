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
 * Main view page for a quickpoll activity.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('quickpoll', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$poll = $DB->get_record('quickpoll', ['id' => $cm->instance], '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/quickpoll:viewresults', $context);

$PAGE->set_url('/mod/quickpoll/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($poll->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->js_call_amd('mod_quickpoll/poll_renderer', 'init', [(int) $cm->id]);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$renderer = $PAGE->get_renderer('mod_quickpoll');
$data = \mod_quickpoll\local\poll_exporter::export_widget($poll, (int) $cm->id, $context, (int) $USER->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($poll->name));
echo $renderer->render_poll_widget($data);
echo $OUTPUT->footer();
