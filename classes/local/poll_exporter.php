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
 * Exports quickpoll data for Mustache templates and AJAX responses.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpoll\local;

use context_module;
use moodle_url;
use stdClass;

/**
 * Converts poll records into serialisable arrays for UI layers.
 */
class poll_exporter {
    /**
     * Results are visible as soon as the activity is viewed.
     */
    private const SHOW_RESULTS_ALWAYS = 0;

    /**
     * Results are visible after the current user has voted.
     */
    private const SHOW_RESULTS_AFTER_VOTE = 1;

    /**
     * Results are visible only after the poll closes.
     */
    private const SHOW_RESULTS_AFTER_CLOSE = 2;

    /**
     * Builds the full widget context used by view.php.
     *
     * @param stdClass $poll The quickpoll record.
     * @param int $cmid The course-module ID.
     * @param context_module $context The module context.
     * @param int $userid The current user ID.
     * @return array
     */
    public static function export_widget(stdClass $poll, int $cmid, context_module $context, int $userid): array {
        $manager = new poll_manager($poll, $context);
        $canvote = has_capability('mod/quickpoll:vote', $context) && $manager->is_open();
        $canviewresults = self::can_view_results($poll, $context, $userid, $manager);
        $results = self::export_results($poll, $cmid, $context, $canviewresults);
        $hasvoted = self::has_user_voted($poll, $userid);

        return [
            'cmid' => $cmid,
            'name' => format_string($poll->name, true, ['context' => $context]),
            'intro' => format_module_intro('quickpoll', $poll, $cmid),
            'isopen' => $manager->is_open(),
            'isclosed' => $manager->is_closed(),
            'ispending' => $manager->is_pending(),
            'canvote' => $canvote,
            'canviewresults' => $canviewresults,
            'allowmultiple' => !empty($poll->allowmultiple),
            'hasanonymous' => anonymous_helper::should_show_toggle($poll, $context),
            'hasgrade' => !empty($poll->maxgrade),
            'hasvoted' => $hasvoted,
            'grade' => format_float($poll->maxgrade, 2, true, true),
            'message' => self::get_state_message($poll, $manager, $canviewresults, $hasvoted),
            'questions' => self::export_questions($poll, $context, $userid, $canvote, $results),
            'streamurl' => (new moodle_url('/mod/quickpoll/stream.php', ['cmid' => $cmid]))->out(false),
        ];
    }

    /**
     * Exports only questions and options for AJAX consumers.
     *
     * @param stdClass $poll The quickpoll record.
     * @param context_module $context The module context.
     * @param int $userid The current user ID.
     * @return array
     */
    public static function export_questions_only(stdClass $poll, context_module $context, int $userid): array {
        $manager = new poll_manager($poll, $context);
        $canvote = has_capability('mod/quickpoll:vote', $context) && $manager->is_open();

        return [
            'questions' => self::export_questions($poll, $context, $userid, $canvote),
        ];
    }

    /**
     * Exports aggregated results for AJAX and SSE consumers.
     *
     * @param stdClass $poll The quickpoll record.
     * @param int $cmid The course-module ID.
     * @param context_module $context The module context.
     * @param bool|null $canviewresults Optional precomputed visibility.
     * @return array
     */
    public static function export_results(
        stdClass $poll,
        int $cmid,
        context_module $context,
        ?bool $canviewresults = null
    ): array {
        global $USER;

        if ($canviewresults === null) {
            $manager = new poll_manager($poll, $context);
            $canviewresults = self::can_view_results($poll, $context, (int) $USER->id, $manager);
        }

        if (!$canviewresults) {
            return [
                'canviewresults' => false,
                'questions' => [],
            ];
        }

        $aggregator = new result_aggregator($poll, $cmid);
        $counts = $aggregator->get_counts();
        $respondents = $aggregator->get_respondents($context);

        return [
            'canviewresults' => true,
            'questions' => self::build_result_questions($poll, $counts, $respondents),
        ];
    }

    /**
     * Returns whether the current user may see results according to the setting.
     *
     * @param stdClass $poll The quickpoll record.
     * @param context_module $context The module context.
     * @param int $userid The current user ID.
     * @param poll_manager $manager The poll manager.
     * @return bool
     */
    private static function can_view_results(
        stdClass $poll,
        context_module $context,
        int $userid,
        poll_manager $manager
    ): bool {
        require_capability('mod/quickpoll:viewresults', $context);

        if (has_capability('mod/quickpoll:manage', $context)) {
            return true;
        }

        if ((int) $poll->showresults === self::SHOW_RESULTS_AFTER_CLOSE) {
            return $manager->is_closed();
        }

        if ((int) $poll->showresults === self::SHOW_RESULTS_AFTER_VOTE) {
            return self::has_user_voted($poll, $userid);
        }

        return true;
    }

    /**
     * Checks whether a user has submitted at least one answer in the poll.
     *
     * @param stdClass $poll The quickpoll record.
     * @param int $userid The user ID.
     * @return bool
     */
    private static function has_user_voted(stdClass $poll, int $userid): bool {
        global $DB;

        return $DB->record_exists('quickpoll_answers', ['pollid' => $poll->id, 'userid' => $userid]);
    }

    /**
     * Exports questions and options, merging result data when available.
     *
     * @param stdClass $poll The quickpoll record.
     * @param context_module $context The module context.
     * @param int $userid The current user ID.
     * @param bool $canvote Whether voting controls may be shown.
     * @param array|null $results Optional result payload from export_results().
     * @return array
     */
    private static function export_questions(
        stdClass $poll,
        context_module $context,
        int $userid,
        bool $canvote,
        ?array $results = null
    ): array {
        $manager = new poll_manager($poll, $context);
        $questions = $manager->get_questions();
        $resultmap = self::index_result_options($results);
        $exported = [];

        foreach ($questions as $question) {
            $hasvoted = $manager->has_voted($userid, (int) $question->id);
            $options = [];

            foreach ($question->options as $option) {
                $result = $resultmap[(int) $option->id] ?? [];
                $options[] = [
                    'id' => (int) $option->id,
                    'questionid' => (int) $question->id,
                    'text' => format_string($option->optiontext, true, ['context' => $context]),
                    'count' => $result['count'] ?? 0,
                    'percent' => $result['percent'] ?? 0,
                    'respondents' => $result['respondents'] ?? [],
                    'hasrespondents' => !empty($result['respondents']),
                    'canvoteoption' => $canvote && (!empty($poll->allowmultiple) || !$hasvoted),
                ];
            }

            $exported[] = [
                'id' => (int) $question->id,
                'text' => format_string($question->questiontext, true, ['context' => $context]),
                'hasvoted' => $hasvoted,
                'canvotequestion' => $canvote && (!empty($poll->allowmultiple) || !$hasvoted),
                'options' => $options,
            ];
        }

        return $exported;
    }

    /**
     * Converts result questions into an option-id lookup map.
     *
     * @param array|null $results Result payload from export_results().
     * @return array
     */
    private static function index_result_options(?array $results): array {
        if (empty($results['questions'])) {
            return [];
        }

        $map = [];
        foreach ($results['questions'] as $question) {
            foreach ($question['options'] as $option) {
                $map[(int) $option['id']] = $option;
            }
        }

        return $map;
    }

    /**
     * Builds result data grouped by question.
     *
     * @param stdClass $poll The quickpoll record.
     * @param array $counts Count rows indexed by option ID.
     * @param array $respondents Respondent lists indexed by option ID.
     * @return array
     */
    private static function build_result_questions(stdClass $poll, array $counts, array $respondents): array {
        global $DB;

        $questions = $DB->get_records('quickpoll_questions', ['pollid' => $poll->id], 'sortorder ASC');
        if (empty($questions)) {
            return [];
        }

        $questionids = array_keys($questions);
        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $options = $DB->get_records_sql(
            "SELECT * FROM {quickpoll_options}
              WHERE questionid $insql
              ORDER BY questionid, sortorder ASC",
            $params
        );

        $totals = [];
        foreach ($counts as $count) {
            $questionid = (int) $count['questionid'];
            $totals[$questionid] = ($totals[$questionid] ?? 0) + (int) $count['count'];
        }

        $optionsbyquestion = [];
        foreach ($options as $option) {
            $count = $counts[(int) $option->id]['count'] ?? 0;
            $total = $totals[(int) $option->questionid] ?? 0;
            $percent = $total > 0 ? round(($count / $total) * 100, 1) : 0;

            $optionsbyquestion[(int) $option->questionid][] = [
                'id' => (int) $option->id,
                'questionid' => (int) $option->questionid,
                'text' => format_string($option->optiontext),
                'count' => (int) $count,
                'percent' => $percent,
                'respondents' => self::export_respondents($respondents[(int) $option->id] ?? []),
            ];
        }

        $exported = [];
        foreach ($questions as $question) {
            $exported[] = [
                'id' => (int) $question->id,
                'text' => format_string($question->questiontext),
                'total' => $totals[(int) $question->id] ?? 0,
                'options' => $optionsbyquestion[(int) $question->id] ?? [],
            ];
        }

        return $exported;
    }

    /**
     * Exports respondents into template-safe scalar values.
     *
     * @param stdClass[] $respondents Raw respondent objects.
     * @return array
     */
    private static function export_respondents(array $respondents): array {
        $exported = [];

        foreach (array_slice($respondents, 0, 5) as $respondent) {
            $exported[] = [
                'anonymous' => !empty($respondent->anonymous),
                'pictureurl' => $respondent->pictureurl ?? '',
                'fullname' => $respondent->fullname ?? '',
            ];
        }

        if (count($respondents) > 5) {
            $exported[] = [
                'anonymous' => true,
                'pictureurl' => '',
                'fullname' => get_string('additionalresponses', 'mod_quickpoll', count($respondents) - 5),
            ];
        }

        return $exported;
    }

    /**
     * Returns the message describing the current visibility or period state.
     *
     * @param stdClass $poll The quickpoll record.
     * @param poll_manager $manager The poll manager.
     * @param bool $canviewresults Whether results are currently visible.
     * @param bool $hasvoted Whether the current user has voted.
     * @return string
     */
    private static function get_state_message(
        stdClass $poll,
        poll_manager $manager,
        bool $canviewresults,
        bool $hasvoted
    ): string {
        if ($manager->is_closed()) {
            return get_string('pollclosed', 'mod_quickpoll');
        }

        if ($manager->is_pending()) {
            return get_string('pollnotopen', 'mod_quickpoll');
        }

        if (!$canviewresults && (int) $poll->showresults === self::SHOW_RESULTS_AFTER_CLOSE) {
            return get_string('resultshiddenuntilclose', 'mod_quickpoll');
        }

        if (!$canviewresults && !$hasvoted) {
            return get_string('resultshiddenuntilvote', 'mod_quickpoll');
        }

        return '';
    }
}
