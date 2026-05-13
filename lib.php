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
 * Library of interface functions and constants for mod_quickpoll.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the list of features this module supports.
 *
 * @param string $feature FEATURE_xx constant for requested feature.
 * @return bool|null True if supported, false if not, null if unknown.
 */
function quickpoll_supports(string $feature): ?bool {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COMMUNICATION;
        default:
            return null;
    }
}

/**
 * Saves a new quickpoll instance into the database.
 *
 * @param stdClass $data Form data from mod_form.
 * @param mod_quickpoll_mod_form|null $mform The form object (may be null in tests).
 * @return int The new instance ID.
 */
function quickpoll_add_instance(stdClass $data, ?mod_quickpoll_mod_form $mform = null): int {
    global $DB;

    $data->timecreated  = time();
    $data->timemodified = time();
    $data->timeopen     = $data->timeopen ?? 0;
    $data->timeclose    = $data->timeclose ?? 0;

    $pollid = $DB->insert_record('quickpoll', $data);

    quickpoll_grade_item_update($data);
    quickpoll_save_questions($pollid, $data);

    return $pollid;
}

/**
 * Updates an existing quickpoll instance in the database.
 *
 * @param stdClass $data Form data from mod_form.
 * @param mod_quickpoll_mod_form|null $mform The form object (may be null in tests).
 * @return bool True on success.
 */
function quickpoll_update_instance(stdClass $data, ?mod_quickpoll_mod_form $mform = null): bool {
    global $DB;

    $data->id           = $data->instance;
    $data->timemodified = time();
    $data->timeopen     = $data->timeopen ?? 0;
    $data->timeclose    = $data->timeclose ?? 0;

    $DB->update_record('quickpoll', $data);

    quickpoll_grade_item_update($data);
    quickpoll_save_questions($data->id, $data);

    return true;
}

/**
 * Removes a quickpoll instance and all associated data from the database.
 *
 * @param int $id The instance ID to delete.
 * @return bool True on success.
 */
function quickpoll_delete_instance(int $id): bool {
    global $DB;

    if (!$DB->record_exists('quickpoll', ['id' => $id])) {
        return false;
    }

    $DB->delete_records('quickpoll_answers', ['pollid' => $id]);

    $questionids = $DB->get_fieldset_select(
        'quickpoll_questions',
        'id',
        'pollid = :pollid',
        ['pollid' => $id]
    );

    if (!empty($questionids)) {
        [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('quickpoll_options', "questionid $insql", $inparams);
    }

    $DB->delete_records('quickpoll_questions', ['pollid' => $id]);
    $DB->delete_records('quickpoll', ['id' => $id]);

    quickpoll_grade_item_delete((object) ['id' => $id]);

    return true;
}

/**
 * Creates or updates the grade item in the Moodle gradebook.
 *
 * @param stdClass $poll The poll record (must contain id, name, maxgrade).
 * @param mixed $grades GRADE_UPDATE_ITEM_ONLY, a grades array, or null.
 * @return int GRADE_UPDATE_OK or GRADE_UPDATE_FAILED.
 */
function quickpoll_grade_item_update(stdClass $poll, mixed $grades = null): int {
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => $poll->name ?? '',
        'idnumber' => $poll->cmidnumber ?? '',
    ];

    if (empty($poll->maxgrade)) {
        $params['gradetype'] = GRADE_TYPE_NONE;
    } else {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $poll->maxgrade;
        $params['grademin']  = 0;
    }

    if ($grades === GRADE_UPDATE_ITEM_ONLY) {
        $params['reset'] = true;
        $grades          = null;
    }

    return grade_update(
        'mod/quickpoll',
        $poll->course ?? 0,
        'mod',
        'quickpoll',
        $poll->id,
        0,
        $grades,
        $params
    );
}

/**
 * Deletes the grade item from the gradebook.
 *
 * @param stdClass $poll The poll record (must contain id).
 * @return int GRADE_UPDATE_OK or GRADE_UPDATE_FAILED.
 */
function quickpoll_grade_item_delete(stdClass $poll): int {
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/quickpoll',
        $poll->course ?? 0,
        'mod',
        'quickpoll',
        $poll->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Awards the maximum grade to a student who has answered all questions.
 *
 * @param stdClass $poll The poll record.
 * @param int $userid The user to grade.
 * @return int GRADE_UPDATE_OK or GRADE_UPDATE_FAILED.
 */
function quickpoll_update_grades(stdClass $poll, int $userid): int {
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');

    if (empty($poll->maxgrade)) {
        return GRADE_UPDATE_OK;
    }

    $grade = (object) [
        'userid'   => $userid,
        'rawgrade' => (float) $poll->maxgrade,
    ];

    return quickpoll_grade_item_update($poll, [$userid => $grade]);
}

/**
 * Returns all quickpoll instances in a given course (used by index.php).
 *
 * @param int $courseid The course ID.
 * @return stdClass[] Array of poll records.
 */
function quickpoll_get_all_instances_in_course(int $courseid): array {
    global $DB;

    return $DB->get_records_sql(
        "SELECT q.*, cm.id AS coursemodule
           FROM {quickpoll} q
           JOIN {course_modules} cm ON cm.instance = q.id
           JOIN {modules} m ON m.id = cm.module
          WHERE q.course = :course
            AND m.name = 'quickpoll'
          ORDER BY cm.section, q.name",
        ['course' => $courseid]
    );
}

/**
 * Persists questions and their options from mod_form data.
 *
 * mod_form uses flat option field names qoption_0 … qoption_7 (instead of
 * nested arrays) to satisfy repeat_elements() setType requirements.
 * This function reads those flat fields and rebuilds the DB records.
 *
 * Existing questions and options are deleted and re-created on each save.
 * Existing answers are intentionally preserved (they reference option IDs
 * that are stable within a single editing session; answers from a previous
 * version are orphaned if questions/options are changed — acceptable in v1).
 *
 * @param int $pollid The poll instance ID.
 * @param stdClass $data The form data.
 * @return void
 */
function quickpoll_save_questions(int $pollid, stdClass $data): void {
    global $DB;

    // Remove existing questions and options (answers kept intentionally).
    $existingids = $DB->get_fieldset_select(
        'quickpoll_questions',
        'id',
        'pollid = :pollid',
        ['pollid' => $pollid]
    );

    if (!empty($existingids)) {
        [$insql, $inparams] = $DB->get_in_or_equal($existingids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('quickpoll_options', "questionid $insql", $inparams);
    }

    $DB->delete_records('quickpoll_questions', ['pollid' => $pollid]);

    if (empty($data->questiontext) || !is_array($data->questiontext)) {
        return;
    }

    // Mod form generates qoption_0 through qoption_7 (MAX_OPTIONS = 8).
    $maxoptions = 8;
    $now        = time();

    foreach ($data->questiontext as $sortorder => $questiontext) {
        $questiontext = trim((string) $questiontext);
        if ($questiontext === '') {
            continue;
        }

        $questionid = $DB->insert_record('quickpoll_questions', (object) [
            'pollid'       => $pollid,
            'questiontext' => $questiontext,
            'sortorder'    => (int) $sortorder,
            'timecreated'  => $now,
        ]);

        $optionsort = 0;
        for ($i = 0; $i < $maxoptions; $i++) {
            $fieldname  = 'qoption_' . $i;
            $optiontext = trim((string) ($data->{$fieldname}[$sortorder] ?? ''));
            if ($optiontext === '') {
                continue;
            }

            $DB->insert_record('quickpoll_options', (object) [
                'questionid' => $questionid,
                'optiontext' => $optiontext,
                'sortorder'  => $optionsort++,
            ]);
        }
    }
}
