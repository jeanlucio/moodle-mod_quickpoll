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
 * Custom exception class for mod_quickpoll business-rule violations.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_quickpoll\exception;

/**
 * Thrown when a quickpoll business rule is violated (e.g. duplicate vote, period closed).
 *
 * All string keys must exist in lang/en/quickpoll.php.
 */
class poll_exception extends \moodle_exception {
    /**
     * Creates a new poll_exception.
     *
     * @param string $errorcode Key from lang/en/quickpoll.php (e.g. 'errorperiod').
     * @param mixed $a Optional extra data passed to get_string().
     * @param string $link Optional URL to display as a "continue" link.
     * @param string $debuginfo Optional developer-facing debug message.
     */
    public function __construct(
        string $errorcode,
        mixed $a = null,
        string $link = '',
        string $debuginfo = ''
    ) {
        parent::__construct($errorcode, 'mod_quickpoll', $link, $a, $debuginfo);
    }
}
