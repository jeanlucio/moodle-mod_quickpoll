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
 * Core business logic for mod_quickpoll: period validation, duplicate-vote guard and vote submission.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpoll\local;

use mod_quickpoll\exception\poll_exception;
use stdClass;

/**
 * Manages the core voting lifecycle for a quickpoll instance.
 */
class poll_manager {
    /**
     * The poll record from the quickpoll table.
     *
     * @var stdClass
     */
    private stdClass $poll;

    /**
     * The course-module context for capability checks.
     *
     * @var \context_module
     */
    private \context_module $context;

    /**
     * Constructs the manager for a given poll instance.
     *
     * @param stdClass $poll The poll record (must contain id, timeopen, timeclose, anonymous).
     * @param \context_module $context The course-module context.
     */
    public function __construct(stdClass $poll, \context_module $context) {
        $this->poll    = $poll;
        $this->context = $context;
    }

    /**
     * Returns true when the poll is currently open for voting.
     *
     * A poll is open when:
     *   - timeopen is 0 (no open restriction) or has already passed, AND
     *   - timeclose is 0 (no close restriction) or has not yet passed.
     *
     * @return bool
     */
    public function is_open(): bool {
        $now = time();
        $timeopen = (int) $this->poll->timeopen;
        $timeclose = (int) $this->poll->timeclose;

        $openok = ($timeopen === 0 || $timeopen <= $now);
        $closeok = ($timeclose === 0 || $timeclose > $now);

        return $openok && $closeok;
    }

    /**
     * Returns true when the poll period has definitively ended.
     *
     * @return bool
     */
    public function is_closed(): bool {
        $timeclose = (int) $this->poll->timeclose;

        return $timeclose > 0 && time() >= $timeclose;
    }

    /**
     * Returns true when the poll has not yet opened.
     *
     * @return bool
     */
    public function is_pending(): bool {
        $timeopen = (int) $this->poll->timeopen;

        return $timeopen > 0 && time() < $timeopen;
    }

    /**
     * Checks whether a given user has already voted on a specific question.
     *
     * The query is bound to both pollid and questionid so an external ID
     * cannot be used to probe a different instance.
     *
     * @param int $userid The user to check.
     * @param int $questionid The question ID (must belong to this poll).
     * @return bool
     */
    public function has_voted(int $userid, int $questionid): bool {
        global $DB;

        return $DB->record_exists_sql(
            "SELECT 1
               FROM {quickpoll_answers} a
               JOIN {quickpoll_questions} q ON q.id = a.questionid
              WHERE a.userid     = :userid
                AND a.questionid = :questionid
                AND a.pollid     = :pollid
                AND q.pollid     = :pollid2",
            [
                'userid'     => $userid,
                'questionid' => $questionid,
                'pollid'     => $this->poll->id,
                'pollid2'    => $this->poll->id,
            ]
        );
    }

    /**
     * Checks whether a given user has already selected an option in a question.
     *
     * @param int $userid The user to check.
     * @param int $questionid The question ID (must belong to this poll).
     * @param int $optionid The option ID (must belong to the question).
     * @return bool
     */
    public function has_voted_option(int $userid, int $questionid, int $optionid): bool {
        global $DB;

        return $DB->record_exists_sql(
            "SELECT 1
               FROM {quickpoll_answers} a
               JOIN {quickpoll_questions} q ON q.id = a.questionid
              WHERE a.userid     = :userid
                AND a.questionid = :questionid
                AND a.optionid   = :optionid
                AND a.pollid     = :pollid
                AND q.pollid     = :pollid2",
            [
                'userid'     => $userid,
                'questionid' => $questionid,
                'optionid'   => $optionid,
                'pollid'     => $this->poll->id,
                'pollid2'    => $this->poll->id,
            ]
        );
    }

    /**
     * Checks whether the student has answered every question in this poll.
     *
     * @param int $userid The user to check.
     * @return bool
     */
    public function has_answered_all(int $userid): bool {
        global $DB;

        $total = $DB->count_records('quickpoll_questions', ['pollid' => $this->poll->id]);
        if ($total === 0) {
            return false;
        }

        $answered = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT a.questionid)
               FROM {quickpoll_answers} a
               JOIN {quickpoll_questions} q ON q.id = a.questionid
              WHERE a.userid  = :userid
                AND a.pollid  = :pollid
                AND q.pollid  = :pollid2",
            [
                'userid'  => $userid,
                'pollid'  => $this->poll->id,
                'pollid2' => $this->poll->id,
            ]
        );

        return (int) $answered >= $total;
    }

    /**
     * Submits a single answer after running all business-rule validations.
     *
     * Validations (all server-side, regardless of UI state):
     *   1. Poll must be open.
     *   2. User must have the :vote capability.
     *   3. The question must belong to this poll.
     *   4. The option must belong to the question.
     *   5. No duplicate vote for this user/question pair.
     *
     * On success, the answer is persisted and, if the user has now answered
     * all questions, their gradebook entry is updated.
     *
     * @param int $userid The voting user.
     * @param int $questionid The question being answered.
     * @param int $optionid The chosen option.
     * @param bool $anonymous Whether the student requested anonymity.
     * @return stdClass The inserted answer record.
     * @throws poll_exception When any validation fails.
     */
    public function submit_vote(
        int $userid,
        int $questionid,
        int $optionid,
        bool $anonymous
    ): stdClass {
        global $DB;

        if (!$this->is_open()) {
            throw new poll_exception('errorperiod');
        }

        require_capability('mod/quickpoll:vote', $this->context);

        // Verify the question belongs to this poll (instanceid bound check).
        $question = $DB->get_record_sql(
            "SELECT id FROM {quickpoll_questions}
              WHERE id = :id AND pollid = :pollid",
            ['id' => $questionid, 'pollid' => $this->poll->id],
            MUST_EXIST
        );

        // Verify the option belongs to the question (instanceid bound check).
        $DB->get_record_sql(
            "SELECT id FROM {quickpoll_options}
              WHERE id = :id AND questionid = :questionid",
            ['id' => $optionid, 'questionid' => $question->id],
            MUST_EXIST
        );

        $allowmultiple = !empty($this->poll->allowmultiple);
        if (!$allowmultiple && $this->has_voted($userid, $questionid)) {
            throw new poll_exception('errorvoteduplicate');
        }

        if ($allowmultiple && $this->has_voted_option($userid, $questionid, $optionid)) {
            throw new poll_exception('errorvoteduplicate');
        }

        // Anonymous flag is only honoured when the poll allows opt-in.
        $storeanonymous = ($this->poll->anonymous == 1) && $anonymous;

        $answer = (object) [
            'pollid'      => $this->poll->id,
            'questionid'  => $questionid,
            'optionid'    => $optionid,
            'userid'      => $userid,
            'anonymous'   => (int) $storeanonymous,
            'timecreated' => time(),
        ];

        $answer->id = $DB->insert_record('quickpoll_answers', $answer);

        // Award grade when the student has now finished all questions.
        if ($this->has_answered_all($userid)) {
            quickpoll_update_grades($this->poll, $userid);
        }

        return $answer;
    }

    /**
     * Returns all questions for this poll, each with its options pre-loaded.
     *
     * @return stdClass[] Array of question objects, each with an ->options array.
     */
    public function get_questions(): array {
        global $DB;

        $questions = $DB->get_records(
            'quickpoll_questions',
            ['pollid' => $this->poll->id],
            'sortorder ASC'
        );

        if (empty($questions)) {
            return [];
        }

        $questionids = array_keys($questions);
        [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);

        $options = $DB->get_records_sql(
            "SELECT * FROM {quickpoll_options}
              WHERE questionid $insql
              ORDER BY questionid, sortorder ASC",
            $inparams
        );

        foreach ($questions as $question) {
            $question->options = [];
        }

        foreach ($options as $option) {
            $questions[$option->questionid]->options[] = $option;
        }

        return array_values($questions);
    }
}
