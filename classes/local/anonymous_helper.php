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
 * Helper for anonymous-voting rules in mod_quickpoll.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpoll\local;

use context_module;
use stdClass;

/**
 * Centralises all anonymous-voting visibility decisions.
 *
 * Rules (defined in the escopo):
 *   - If the teacher set anonymous = 0 → toggle is never shown; all answers are identified.
 *   - If the teacher set anonymous = 1 (opt-in) → toggle is shown; default is identified;
 *     student may flip to anonymous per answer.
 *   - The stored userid is NEVER returned to non-teacher clients regardless of the flag.
 *   - Teachers with :viewanonymousresults see real identities even for anonymous answers.
 */
class anonymous_helper {
    /**
     * Returns true when the poll allows students to choose anonymity.
     *
     * @param stdClass $poll The poll record (must contain anonymous field).
     * @return bool
     */
    public static function poll_allows_anonymous(stdClass $poll): bool {
        return (int) $poll->anonymous === 1;
    }

    /**
     * Returns true when the anonymous toggle should be rendered for the current student.
     *
     * The toggle is shown only when the poll allows opt-in AND the user has the :vote
     * capability (i.e. is a student, not a teacher viewing the poll).
     *
     * @param stdClass $poll The poll record.
     * @param context_module $context The module context.
     * @return bool
     */
    public static function should_show_toggle(stdClass $poll, context_module $context): bool {
        if (!self::poll_allows_anonymous($poll)) {
            return false;
        }

        return has_capability('mod/quickpoll:vote', $context);
    }

    /**
     * Determines whether a given answer should be rendered as anonymous in the UI.
     *
     * Returns true (hide identity) when:
     *   - The answer was stored as anonymous = 1, AND
     *   - The viewing user does NOT have the :viewanonymousresults capability.
     *
     * Returns false (show identity) in all other cases, including when the teacher
     * views the poll regardless of the stored flag.
     *
     * @param stdClass $answer The answer record (must contain anonymous field).
     * @param context_module $context The module context of the viewing user.
     * @return bool
     */
    public static function should_hide_identity(stdClass $answer, context_module $context): bool {
        if ((int) $answer->anonymous !== 1) {
            return false;
        }

        return !has_capability('mod/quickpoll:viewanonymousresults', $context);
    }

    /**
     * Sanitises the anonymous flag value coming from an untrusted source (e.g. WS param).
     *
     * Always returns false when the poll does not allow opt-in, regardless of the
     * value supplied by the client. This prevents a client from forcing anonymous
     * storage on polls where the teacher disabled the feature.
     *
     * @param stdClass $poll The poll record.
     * @param bool $clientvalue The value sent by the client (e.g. from the toggle).
     * @return bool
     */
    public static function sanitise_anonymous_flag(stdClass $poll, bool $clientvalue): bool {
        if (!self::poll_allows_anonymous($poll)) {
            return false;
        }

        return $clientvalue;
    }
}
