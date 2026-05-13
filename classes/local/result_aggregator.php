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
 * Aggregates vote counts for a quickpoll instance, using MUC Cache to reduce DB load.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpoll\local;

use cache;
use stdClass;

/**
 * Reads and caches aggregated vote counts per option for a quickpoll instance.
 *
 * Cache area: mod_quickpoll / pollcounts (defined in db/caches.php).
 * Cache key : quickpoll_counts_{cmid}
 * TTL       : 30 s (fallback safety; invalidated immediately on each new vote).
 */
class result_aggregator {
    /**
     * Prefix used for all MUC cache keys managed by this class.
     */
    private const CACHE_KEY_PREFIX = 'quickpoll_counts_';

    /**
     * The poll record.
     *
     * @var stdClass
     */
    private stdClass $poll;

    /**
     * The course-module ID used as the cache key discriminator.
     *
     * @var int
     */
    private int $cmid;

    /**
     * Constructs the aggregator for a given poll instance.
     *
     * @param stdClass $poll The poll record (must contain id).
     * @param int $cmid The course-module ID.
     */
    public function __construct(stdClass $poll, int $cmid) {
        $this->poll = $poll;
        $this->cmid = $cmid;
    }

    /**
     * Returns the MUC cache instance for the pollcounts area.
     *
     * @return cache
     */
    private function get_cache(): cache {
        return cache::make('mod_quickpoll', 'pollcounts');
    }

    /**
     * Builds the cache key for the current cm instance.
     *
     * @return string
     */
    private function cache_key(): string {
        return self::CACHE_KEY_PREFIX . $this->cmid;
    }

    /**
     * Invalidates the cached counts for this instance.
     *
     * Must be called immediately after every successful vote submission so
     * the next SSE push reflects the new state.
     *
     * @return void
     */
    public function invalidate(): void {
        $this->get_cache()->delete($this->cache_key());
    }

    /**
     * Returns aggregated vote counts for every option in this poll.
     *
     * Results are served from the MUC Cache when available; otherwise the DB
     * is queried and the result is stored in the cache.
     *
     * Return shape:
     * [
     *   optionid => [
     *     'count'      => int,       // total votes for this option
     *     'questionid' => int,
     *     'optionid'   => int,
     *   ],
     *   ...
     * ]
     *
     * @return array<int, array<string, int>>
     */
    public function get_counts(): array {
        $cache = $this->get_cache();
        $key   = $this->cache_key();

        $cached = $cache->get($key);
        if ($cached !== false) {
            return $cached;
        }

        $counts = $this->fetch_counts_from_db();
        $cache->set($key, $counts);

        return $counts;
    }

    /**
     * Queries the DB for option-level vote counts, bound to this poll instance.
     *
     * The JOIN on quickpoll_questions ensures the query cannot be tricked into
     * counting answers from a different poll via a crafted optionid.
     *
     * @return array<int, array<string, int>>
     */
    private function fetch_counts_from_db(): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT o.id AS optionid,
                    o.questionid,
                    COUNT(a.id) AS votecount
               FROM {quickpoll_options} o
               JOIN {quickpoll_questions} q ON q.id = o.questionid
          LEFT JOIN {quickpoll_answers}   a ON a.optionid   = o.id
                                           AND a.questionid = o.questionid
                                           AND a.pollid     = :pollid
              WHERE q.pollid = :pollid2
              GROUP BY o.id, o.questionid
              ORDER BY o.questionid, o.sortorder",
            [
                'pollid'  => $this->poll->id,
                'pollid2' => $this->poll->id,
            ]
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->optionid] = [
                'optionid'   => (int) $row->optionid,
                'questionid' => (int) $row->questionid,
                'count'      => (int) $row->votecount,
            ];
        }

        return $counts;
    }

    /**
     * Returns the list of respondents for each option, respecting the anonymous flag.
     *
     * For non-anonymous answers the user picture URL and display name are included.
     * For anonymous answers only a placeholder is returned so the front-end can
     * render the lock icon without exposing the identity.
     *
     * Teachers with the mod/quickpoll:viewanonymousresults capability always
     * receive the full identity even for anonymous answers.
     *
     * @param \context_module $context The module context for capability checks.
     * @return array<int, array<int, stdClass>> optionid => list of respondent objects.
     */
    public function get_respondents(\context_module $context): array {
        global $DB, $OUTPUT;

        $canviewanon = has_capability('mod/quickpoll:viewanonymousresults', $context);

        $rows = $DB->get_records_sql(
            "SELECT a.id,
                    a.optionid,
                    a.userid,
                    a.anonymous,
                    u.firstname,
                    u.lastname,
                    u.picture,
                    u.imagealt,
                    u.email
               FROM {quickpoll_answers} a
               JOIN {user} u ON u.id = a.userid
               JOIN {quickpoll_questions} q ON q.id = a.questionid
              WHERE a.pollid  = :pollid
                AND q.pollid  = :pollid2
              ORDER BY a.optionid, a.timecreated",
            [
                'pollid'  => $this->poll->id,
                'pollid2' => $this->poll->id,
            ]
        );

        $respondents = [];

        foreach ($rows as $row) {
            $optionid = (int) $row->optionid;

            if ((int) $row->anonymous === 1 && !$canviewanon) {
                $respondents[$optionid][] = (object) [
                    'anonymous'  => true,
                    'pictureurl' => null,
                    'fullname'   => null,
                ];
                continue;
            }

            $user            = new stdClass();
            $user->id        = $row->userid;
            $user->firstname = $row->firstname;
            $user->lastname  = $row->lastname;
            $user->picture   = $row->picture;
            $user->imagealt  = $row->imagealt;
            $user->email     = $row->email;

            $userpicture             = new \user_picture($user);
            $userpicture->size       = 35;
            $userpicture->includetoken = true;

            $respondents[$optionid][] = (object) [
                'anonymous'  => (int) $row->anonymous === 1,
                'pictureurl' => $userpicture->get_url($OUTPUT->get_page())->out(false),
                'fullname'   => fullname($user),
            ];
        }

        return $respondents;
    }
}
