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
 * External function for reading quickpoll questions.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpoll\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_quickpoll\local\poll_exporter;

/**
 * Returns the question and option structure for a quickpoll instance.
 */
class get_questions extends external_api {
    /**
     * Defines execute() parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Returns all questions and options for the requested activity.
     *
     * @param int $cmid The course-module ID.
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB, $USER;

        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id('quickpoll', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/quickpoll:viewresults', $context);

        $poll = $DB->get_record('quickpoll', ['id' => $cm->instance], '*', MUST_EXIST);

        return poll_exporter::export_questions_only($poll, $context, (int) $USER->id);
    }

    /**
     * Defines execute() return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'questions' => new external_multiple_structure(self::question_structure()),
        ]);
    }

    /**
     * Defines a question structure.
     *
     * @return external_single_structure
     */
    private static function question_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Question id'),
            'text' => new external_value(PARAM_RAW, 'Question text'),
            'hasvoted' => new external_value(PARAM_BOOL, 'Whether the user has voted in this question'),
            'canvotequestion' => new external_value(PARAM_BOOL, 'Whether the user may vote in this question'),
            'options' => new external_multiple_structure(self::option_structure()),
        ]);
    }

    /**
     * Defines an option structure.
     *
     * @return external_single_structure
     */
    private static function option_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Option id'),
            'questionid' => new external_value(PARAM_INT, 'Question id'),
            'text' => new external_value(PARAM_RAW, 'Option text'),
            'count' => new external_value(PARAM_INT, 'Current vote count'),
            'percent' => new external_value(PARAM_FLOAT, 'Current vote percentage'),
            'respondents' => new external_multiple_structure(self::respondent_structure()),
            'hasrespondents' => new external_value(PARAM_BOOL, 'Whether the option has respondents'),
            'canvoteoption' => new external_value(PARAM_BOOL, 'Whether the user may choose this option'),
        ]);
    }

    /**
     * Defines a respondent structure.
     *
     * @return external_single_structure
     */
    private static function respondent_structure(): external_single_structure {
        return new external_single_structure([
            'anonymous' => new external_value(PARAM_BOOL, 'Whether the respondent is anonymous'),
            'pictureurl' => new external_value(PARAM_URL, 'User picture URL', VALUE_OPTIONAL),
            'fullname' => new external_value(PARAM_TEXT, 'User full name or placeholder', VALUE_OPTIONAL),
        ]);
    }
}
