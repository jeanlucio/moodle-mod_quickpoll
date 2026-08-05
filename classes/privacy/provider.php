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
 * Privacy provider implementation for mod_quickpoll.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpoll\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_quickpoll.
 *
 * Personal data is stored in quickpoll_answers (userid, optionid, anonymous,
 * timecreated). The userid is always stored, even for polls that allow
 * anonymous answers, to prevent duplicate votes — it is simply not exposed
 * to non-teacher web services in that case.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata about personal data stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('quickpoll_answers', [
            'userid'      => 'privacy:metadata:quickpoll_answers:userid',
            'optionid'    => 'privacy:metadata:quickpoll_answers:optionid',
            'anonymous'   => 'privacy:metadata:quickpoll_answers:anonymous',
            'timecreated' => 'privacy:metadata:quickpoll_answers:timecreated',
        ], 'privacy:metadata:quickpoll_answers');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {quickpoll_answers} qa
                  JOIN {quickpoll} qp ON qp.id = qa.pollid
                  JOIN {modules} m ON m.name = :activityname
                  JOIN {course_modules} cm ON cm.instance = qp.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE qa.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'activityname' => 'quickpoll',
            'modlevel'     => CONTEXT_MODULE,
            'userid'       => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist to populate.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $sql = "SELECT qa.userid
                  FROM {quickpoll_answers} qa
                  JOIN {quickpoll} qp ON qp.id = qa.pollid
                  JOIN {modules} m ON m.name = :activityname
                  JOIN {course_modules} cm ON cm.instance = qp.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel
                 WHERE ctx.id = :contextid";
        $userlist->add_from_sql('userid', $sql, [
            'activityname' => 'quickpoll',
            'modlevel'     => CONTEXT_MODULE,
            'contextid'    => $context->id,
        ]);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $contexts = array_reduce($contextlist->get_contexts(), function (array $carry, \context $context): array {
            if ($context->contextlevel == CONTEXT_MODULE) {
                $carry[$context->id] = $context;
            }
            return $carry;
        }, []);

        if (empty($contexts)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal(array_keys($contexts), SQL_PARAMS_NAMED, 'ctx');

        $sql = "SELECT qa.id, qa.anonymous, qa.timecreated, ctx.id AS contextid,
                       qq.questiontext, qo.optiontext
                  FROM {quickpoll_answers} qa
                  JOIN {quickpoll} qp ON qp.id = qa.pollid
                  JOIN {quickpoll_questions} qq ON qq.id = qa.questionid
                  JOIN {quickpoll_options} qo ON qo.id = qa.optionid
                  JOIN {modules} m ON m.name = 'quickpoll'
                  JOIN {course_modules} cm ON cm.instance = qp.id AND cm.module = m.id
                  JOIN {context} ctx ON ctx.instanceid = cm.id
                 WHERE ctx.id $insql
                   AND qa.userid = :userid";
        $records = $DB->get_recordset_sql($sql, array_merge($inparams, ['userid' => $userid]));

        $allanswers = [];
        foreach ($records as $record) {
            $allanswers[$record->contextid][] = (object) [
                'question'    => $record->questiontext,
                'answer'      => $record->optiontext,
                'anonymous'   => transform::yesno($record->anonymous),
                'timecreated' => transform::datetime($record->timecreated),
            ];
        }
        $records->close();

        foreach ($allanswers as $contextid => $answers) {
            writer::with_context($contexts[$contextid])->export_data(
                [get_string('privacy:metadata:quickpoll_answers', 'mod_quickpoll')],
                (object) ['answers' => $answers]
            );
        }
    }

    /**
     * Delete all user data for all users in the specified context.
     *
     * @param \context $context The context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('quickpoll', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('quickpoll_answers', ['pollid' => $cm->instance]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        $instanceids = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('quickpoll', $context->instanceid);
            if ($cm) {
                $instanceids[] = (int) $cm->instance;
            }
        }

        if (empty($instanceids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED, 'qp');
        $DB->delete_records_select(
            'quickpoll_answers',
            "pollid $insql AND userid = :userid",
            array_merge($inparams, ['userid' => $userid])
        );
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if (!is_a($context, \context_module::class)) {
            return;
        }

        $cm = get_coursemodule_from_id('quickpoll', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $DB->delete_records_select(
            'quickpoll_answers',
            "pollid = :pollid AND userid $insql",
            array_merge(['pollid' => $cm->instance], $inparams)
        );
    }
}
