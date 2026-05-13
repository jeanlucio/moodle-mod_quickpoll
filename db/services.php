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
 * External function definitions for mod_quickpoll.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_quickpoll_get_questions' => [
        'classname'     => 'mod_quickpoll\external\get_questions',
        'description'   => 'Return the questions and options for a Quick Poll activity.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'mod/quickpoll:viewresults',
        'loginrequired' => true,
    ],
    'mod_quickpoll_get_results' => [
        'classname'     => 'mod_quickpoll\external\get_results',
        'description'   => 'Return aggregated results for a Quick Poll activity.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'mod/quickpoll:viewresults',
        'loginrequired' => true,
    ],
    'mod_quickpoll_submit_vote' => [
        'classname'     => 'mod_quickpoll\external\submit_vote',
        'description'   => 'Submit a vote for a Quick Poll activity.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/quickpoll:vote',
        'loginrequired' => true,
    ],
];
