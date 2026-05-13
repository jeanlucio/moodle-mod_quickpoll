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
 * External function for submitting quickpoll votes.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpoll\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_quickpoll\local\anonymous_helper;
use mod_quickpoll\local\poll_exporter;
use mod_quickpoll\local\poll_manager;
use mod_quickpoll\local\result_aggregator;

/**
 * Validates and records a quickpoll vote.
 */
class submit_vote extends external_api {
    /**
     * Defines execute() parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'questionid' => new external_value(PARAM_INT, 'Question id'),
            'optionid' => new external_value(PARAM_INT, 'Option id'),
            'anonymous' => new external_value(PARAM_BOOL, 'Whether the user requested anonymity', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Submits a single vote and returns the refreshed result payload.
     *
     * @param int $cmid The course-module ID.
     * @param int $questionid The question ID.
     * @param int $optionid The selected option ID.
     * @param bool $anonymous Whether the user requested anonymity.
     * @return array
     */
    public static function execute(int $cmid, int $questionid, int $optionid, bool $anonymous = false): array {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/mod/quickpoll/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'questionid' => $questionid,
            'optionid' => $optionid,
            'anonymous' => $anonymous,
        ]);

        $cm = get_coursemodule_from_id('quickpoll', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quickpoll:vote', $context);

        $poll = $DB->get_record('quickpoll', ['id' => $cm->instance], '*', MUST_EXIST);
        $manager = new poll_manager($poll, $context);
        $isanonymous = anonymous_helper::sanitise_anonymous_flag($poll, $params['anonymous']);

        $answer = $manager->submit_vote(
            (int) $USER->id,
            $params['questionid'],
            $params['optionid'],
            $isanonymous
        );

        $aggregator = new result_aggregator($poll, (int) $cm->id);
        $aggregator->invalidate();

        return [
            'success' => true,
            'message' => get_string('votesubmitted', 'mod_quickpoll'),
            'answerid' => (int) $answer->id,
            'results' => poll_exporter::export_results($poll, (int) $cm->id, $context),
        ];
    }

    /**
     * Defines execute() return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the vote was stored'),
            'message' => new external_value(PARAM_TEXT, 'User-facing response message'),
            'answerid' => new external_value(PARAM_INT, 'Inserted answer id'),
            'results' => get_results::execute_returns(),
        ]);
    }
}
