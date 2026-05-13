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
 * Form definition for creating and editing a quickpoll instance.
 *
 * @package    mod_quickpoll
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Form for adding or editing a mod_quickpoll activity.
 */
class mod_quickpoll_mod_form extends moodleform_mod {
    /** Maximum number of questions allowed per poll. */
    private const MAX_QUESTIONS = 10;

    /** Minimum number of options required per question. */
    private const MIN_OPTIONS = 2;

    /** Maximum number of options allowed per question. */
    private const MAX_OPTIONS = 8;

    /**
     * Defines the form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        // General.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Voting period.
        $mform->addElement('header', 'votingperiodheader', get_string('votingperiod', 'mod_quickpoll'));
        $mform->setExpanded('votingperiodheader');

        $mform->addElement('date_time_selector', 'timeopen', get_string('timeopen', 'mod_quickpoll'), ['optional' => true]);
        $mform->addHelpButton('timeopen', 'timeopen', 'mod_quickpoll');

        $mform->addElement('date_time_selector', 'timeclose', get_string('timeclose', 'mod_quickpoll'), ['optional' => true]);
        $mform->addHelpButton('timeclose', 'timeclose', 'mod_quickpoll');

        // Poll settings.
        $mform->addElement('header', 'pollsettingsheader', get_string('questionsheader', 'mod_quickpoll'));
        $mform->setExpanded('pollsettingsheader');

        $mform->addElement('select', 'anonymous', get_string('anonymous', 'mod_quickpoll'), [
            0 => get_string('anonymousdisabled', 'mod_quickpoll'),
            1 => get_string('anonymousoptin', 'mod_quickpoll'),
        ]);
        $mform->setDefault('anonymous', 0);
        $mform->addHelpButton('anonymous', 'anonymous', 'mod_quickpoll');

        $mform->addElement('advcheckbox', 'allowmultiple', get_string('allowmultiple', 'mod_quickpoll'));
        $mform->setDefault('allowmultiple', 0);
        $mform->addHelpButton('allowmultiple', 'allowmultiple', 'mod_quickpoll');

        $mform->addElement('select', 'showresults', get_string('showresults', 'mod_quickpoll'), [
            0 => get_string('showresults_always', 'mod_quickpoll'),
            1 => get_string('showresults_aftervote', 'mod_quickpoll'),
            2 => get_string('showresults_afterclose', 'mod_quickpoll'),
        ]);
        $mform->setDefault('showresults', 0);
        $mform->addHelpButton('showresults', 'showresults', 'mod_quickpoll');

        // Questions repeater.
        $mform->addElement('header', 'questionsrepeaterheader', get_string('questionsheader', 'mod_quickpoll'));
        $mform->setExpanded('questionsrepeaterheader');

        $this->add_questions_repeater($mform);

        // Grade.
        $mform->addElement('text', 'maxgrade', get_string('maxgrade', 'mod_quickpoll'), ['size' => '10']);
        $mform->setType('maxgrade', PARAM_FLOAT);
        $mform->setDefault('maxgrade', 0);
        $mform->addHelpButton('maxgrade', 'maxgrade', 'mod_quickpoll');

        $this->standard_grading_coursemodule_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds the dynamic question/option repeater to the form.
     *
     * repeat_elements() requires flat field names — nested brackets like
     * optiontext[{no}][] break the internal setType resolution (E_USER_NOTICE
     * "Did you remember to call setType()"). Each repeat block therefore uses
     * flat names qoption_0 … qoption_7.
     *
     * lib.php::quickpoll_save_questions() reads these flat names and assembles
     * the DB records.
     *
     * @param \MoodleQuickForm $mform The form object.
     * @return void
     */
    private function add_questions_repeater(\MoodleQuickForm $mform): void {
        $repeatarray = [];

        $repeatarray[] = $mform->createElement(
            'text',
            'questiontext',
            get_string('questionlabel', 'mod_quickpoll', '{no}'),
            ['size' => '64', 'class' => 'mod-quickpoll-question-text']
        );

        for ($i = 0; $i < self::MAX_OPTIONS; $i++) {
            $repeatarray[] = $mform->createElement(
                'text',
                'qoption_' . $i,
                get_string('questionlabel', 'mod_quickpoll', ($i + 1)),
                ['size' => '48', 'class' => 'mod-quickpoll-option-text']
            );
        }

        // Every field in the repeat block needs an explicit type entry so
        // Moodle's setType resolver never falls back to PARAM_RAW.
        $repeatoptions = ['questiontext' => ['type' => PARAM_TEXT]];
        for ($i = 0; $i < self::MAX_OPTIONS; $i++) {
            $repeatoptions['qoption_' . $i] = ['type' => PARAM_TEXT];
        }

        $this->repeat_elements(
            $repeatarray,
            1,
            $repeatoptions,
            'questioncount',
            'addquestion',
            1,
            get_string('questionsheader', 'mod_quickpoll'),
            true,
            'deletequestion'
        );
    }

    /**
     * Validates the submitted form data.
     *
     * @param array $data Submitted form values.
     * @param array $files Submitted files.
     * @return array field => error message pairs.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        // At least one question with a non-empty text must be present.
        $hasquestion = false;
        if (!empty($data['questiontext']) && is_array($data['questiontext'])) {
            foreach ($data['questiontext'] as $qtext) {
                if (trim((string) $qtext) !== '') {
                    $hasquestion = true;
                    break;
                }
            }
        }

        if (!$hasquestion) {
            $errors['questioncount'] = get_string('errornoquestions', 'mod_quickpoll');
        }

        // Timeclose must come after timeopen when both are set.
        if (
            !empty($data['timeopen']) &&
            !empty($data['timeclose']) &&
            $data['timeclose'] <= $data['timeopen']
        ) {
            $errors['timeclose'] = get_string('errorperiod', 'mod_quickpoll');
        }

        return $errors;
    }

    /**
     * Preprocesses the data before it is set in the form (used when editing).
     *
     * Signature intentionally matches the parent moodleform_mod declaration
     * (no array type hint on the parameter) to stay compatible across
     * Moodle 4.5 and 5.x.
     *
     * @param array $defaultvalues Form data passed by reference.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        parent::data_preprocessing($defaultvalues);

        if (empty($this->current->instance)) {
            return;
        }

        global $DB;

        $questions = $DB->get_records(
            'quickpoll_questions',
            ['pollid' => $this->current->instance],
            'sortorder ASC'
        );

        if (empty($questions)) {
            return;
        }

        $questionids      = array_keys($questions);
        [$insql, $params] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED);

        $options = $DB->get_records_sql(
            "SELECT * FROM {quickpoll_options}
              WHERE questionid $insql
              ORDER BY questionid, sortorder",
            $params
        );

        // Group options by question id for fast lookup.
        $optionsbyquestion = [];
        foreach ($options as $opt) {
            $optionsbyquestion[(int) $opt->questionid][] = $opt->optiontext;
        }

        foreach (array_values($questions) as $idx => $question) {
            $defaultvalues['questiontext'][$idx] = $question->questiontext;

            $qopts = $optionsbyquestion[(int) $question->id] ?? [];
            for ($i = 0; $i < self::MAX_OPTIONS; $i++) {
                $defaultvalues['qoption_' . $i][$idx] = $qopts[$i] ?? '';
            }
        }

        $defaultvalues['questioncount'] = count($questions);
    }
}
