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
 * English language strings for mod_quickpoll.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// phpcs:disable moodle.Files.LineLength

$string['additionalresponses'] = '+{$a} more';
$string['allowmultiple'] = 'Allow multiple choices per question';
$string['allowmultiple_help'] = 'When enabled, students may select more than one option per question.';
$string['anonymous'] = 'Anonymous voting';
$string['anonymous_help'] = 'When enabled, students may choose to hide their identity from peers. Their user ID is always stored server-side to prevent duplicate votes.';
$string['anonymousdisabled'] = 'Disabled — all responses are always identified';
$string['anonymousoptin'] = 'Opt-in — students may choose to be anonymous';
$string['answered'] = 'Answered';
$string['answeredby'] = 'Answered by {$a} students';
$string['answersnow'] = '{$a} responses so far';
$string['closingon'] = 'Closes on {$a}';
$string['errornoquestions'] = 'You must add at least one question before saving.';
$string['errorperiod'] = 'The voting period has ended or has not started yet.';
$string['errorvoteduplicate'] = 'You have already voted in this poll.';
$string['live'] = 'Live';
$string['maxgrade'] = 'Grade for responding';
$string['maxgrade_help'] = 'Students receive this grade automatically upon submitting all answers. Set to 0 to disable grading.';
$string['modulename'] = 'Quick Poll';
$string['modulename_help'] = 'The Quick Poll activity lets teachers create one or more questions with predefined options. Students vote directly from the course page and see live results updating in real time, similar to WhatsApp polls.';
$string['modulenameplural'] = 'Quick Polls';
$string['noanswersyet'] = 'No responses yet.';
$string['openingon'] = 'Opens on {$a}';
$string['optionanonymous'] = 'Hide my identity for this response';
$string['pluginadministration'] = 'Quick Poll administration';
$string['pluginname'] = 'Quick Poll';
$string['pointsbadge'] = '{$a} points';
$string['pollclosed'] = 'This poll is closed.';
$string['pollnotopen'] = 'This poll is not open yet.';
$string['privacy:metadata:quickpoll_answers'] = 'Records each vote cast by a student, including whether the student chose to be anonymous.';
$string['privacy:metadata:quickpoll_answers:anonymous'] = 'Whether the student requested anonymity for this answer.';
$string['privacy:metadata:quickpoll_answers:optionid'] = 'The option chosen by the student.';
$string['privacy:metadata:quickpoll_answers:pollid'] = 'The poll instance this answer belongs to.';
$string['privacy:metadata:quickpoll_answers:questionid'] = 'The question this answer is for.';
$string['privacy:metadata:quickpoll_answers:timecreated'] = 'The time the answer was submitted.';
$string['privacy:metadata:quickpoll_answers:userid'] = 'The user who submitted the answer.';
$string['questionlabel'] = 'Question {$a}';
$string['questionsheader'] = 'Questions';
$string['quickpoll:addinstance'] = 'Add a Quick Poll activity';
$string['quickpoll:manage'] = 'Manage Quick Poll activities';
$string['quickpoll:viewanonymousresults'] = 'View the identity behind anonymous votes';
$string['quickpoll:viewresults'] = 'View poll results';
$string['quickpoll:vote'] = 'Submit a vote';
$string['resultshiddenuntilclose'] = 'Results will be shown after the poll closes.';
$string['resultshiddenuntilvote'] = 'Results will be shown after you vote.';
$string['showresults'] = 'Show results';
$string['showresults_afterclose'] = 'After the poll closes';
$string['showresults_aftervote'] = 'After the student votes';
$string['showresults_always'] = 'Always';
$string['showresults_help'] = 'Controls when students can see the aggregated results.';
$string['timeclose'] = 'Close date';
$string['timeclose_help'] = 'The date and time after which voting is no longer accepted. Leave at 0 to keep the poll open indefinitely.';
$string['timeopen'] = 'Open date';
$string['timeopen_help'] = 'The date and time from which students may vote. Leave at 0 to open immediately.';
$string['votesubmitted'] = 'Your vote has been recorded.';
$string['votingperiod'] = 'Voting period';
