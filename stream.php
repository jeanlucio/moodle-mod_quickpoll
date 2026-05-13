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
 * Server-Sent Events endpoint for quickpoll live results.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);

require(__DIR__ . '/../../config.php');

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('quickpoll', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$poll = $DB->get_record('quickpoll', ['id' => $cm->instance], '*', MUST_EXIST);

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/quickpoll:viewresults', $context);

$PAGE->set_context($context);
$PAGE->set_url('/mod/quickpoll/stream.php', ['cmid' => $cmid]);

@set_time_limit(0);
@ini_set('zlib.output_compression', '0');

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');

while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

$manager = new \mod_quickpoll\local\poll_manager($poll, $context);

for ($i = 0; $i < 30; $i++) {
    if ($manager->is_closed()) {
        echo "event: closed\n";
        echo 'data: ' . json_encode(['closed' => true]) . "\n\n";
        flush();
        break;
    }

    $payload = \mod_quickpoll\local\poll_exporter::export_results($poll, (int) $cm->id, $context);

    echo "event: results\n";
    echo 'data: ' . json_encode($payload) . "\n\n";
    flush();
    sleep(2);
}
