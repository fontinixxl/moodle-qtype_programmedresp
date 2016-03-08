<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Defines the editing form for the programmedresp question type.
 *
 * @package    qtype
 * @subpackage programmedresp
 * @copyright 2016 Gerard Cuello (gerard.urv@gmail.com)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/programmedresp/lib.php');
require_once($CFG->dirroot . '/question/type/programmedresp/programmedresp_output.class.php');

/**
 * programmedresp question editing form definition.
 *
 * @copyright 2016 Gerard Cuello (gerard.urv@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_programmedresp_edit_form extends question_edit_form {

    private $quizid;

    public function __construct($submiturl, $question, $category, $contexts, $formeditable = true) {

        $installed = is_qtype_linkerdesc_installed();
        $cmid = optional_param('cmid', 0, PARAM_INT);

        // Conditions to get a valid quiz id:
        //  - Qtype_linkerdesc question must be installed.
        //  - Valid course module must be submitted and it must be a 'quiz' activity.
        $installed && $cmid && ($this->quizid = programmedresp_getquiz_from_cm($cmid));

        // TODO: provar d'afegir el quizid a $question: $question->quizid = quizid;
        parent::__construct($submiturl, $question, $category, $contexts, $formeditable);
    }

    protected function definition_inner($mform) {
        global $CFG, $DB, $PAGE;

        $PAGE->requires->js('/question/type/programmedresp/script.js');

        // TODO: Refacor it with something more clean
        // Adding required wwwroot and quizid vars to be accessible from script.js
        echo "<script type=\"text/javascript\">//<![CDATA[\n" .
        "this.wwwroot = '" . $CFG->wwwroot . "';\n" .
        "this.quizid= '" . $this->quizid . "';\n" .
        "//]]></script>\n";

        $mform->addElement('hidden', 'quizid', $this->quizid);
        $mform->setType('quizid', PARAM_INT);

        $outputmanager = new prgrammedresp_output($mform);

        $editingjsparam = 'false';
        // In a new question the vars div should be loaded
        if (!empty($this->question->id)) {
            $editingjsparam = 'true';
        }

        // Button label
        if (!empty($this->question->id)) {
            $buttonlabel = get_string('refreshvarsvalues', 'qtype_programmedresp');
        } else {
            $buttonlabel = get_string('assignvarsvalues', 'qtype_programmedresp');
        }

        $varsattrs = array('onclick' => 'display_vars(this, ' . $editingjsparam . ');');
        $mform->addElement('button', 'vars', $buttonlabel, $varsattrs);

        // Link to fill vars data
        $mform->addElement('header', 'varsheader', get_string("varsvalues", "qtype_programmedresp"));

        $mform->addElement('html', '<div id="id_vars_content">');
        if (!empty($this->question->id)) {
            $outputmanager->display_vars($this->question->questiontext,
                    $this->question->options->args, $this->question->options->concatvars);
        }
        $mform->addElement('html', '</div>');

        // Functions header
        $mform->addElement('header', 'functionheader', get_string("assignfunction", "qtype_programmedresp"));

        // Data
        $categories = $DB->get_records('qtype_programmedresp_fcat', array(), 'id ASC', 'id, parent, name');
        // Category options
        $catoptions = array(0 => '&nbsp;(' . get_string('selectcategory', 'qtype_programmedresp') . ')&nbsp;');
        if ($categories) {
            foreach ($categories as $key => $cat) {
                if (empty($catoptions[$cat->id])) {
                    $catoptions[$cat->id] = $cat->name;
                    unset($categories[$key]);
                    programmedresp_add_child_categories($cat->id, $catoptions, $categories);
                }
            }
        }
        // Category select
        $catattrs['onchange'] = 'update_addfunctionurl();return display_functionslist(this);';
        $mform->addElement('select', 'functioncategory',
                get_string('functioncategory', 'qtype_programmedresp'), $catoptions, $catattrs);

        // Dirty hack to add the function (added later through ajax)
        if (empty($this->question->id)) {
            $mform->addElement('hidden', 'programmedrespfid');
            $mform->setType('programmedrespfid', PARAM_INT);
        }

        // Link to add a category
        $caneditfunctions = has_capability('moodle/question:config', context_system::instance());
        if ($caneditfunctions) {
            $addcategoryurl = $CFG->wwwroot . '/question/type/programmedresp/manage.php?action=addcategory';
            $onclick = "window.open(this.href, this.target, 'menubar=0,location=0,scrollbars,resizable,width=500,height=600', true);return false;";
            $categorylink = '<a href="' . $addcategoryurl . '" onclick="' . $onclick . '" target="addcategory">' . get_string('addcategory', 'qtype_programmedresp') . '</a>';
            $mform->addElement('html', '<div class="fitem"><div class="fitemtitle"></div><div class="felement">' . $categorylink . '<br/><br/></div></div>');
        }

        // Function list
        $mform->addElement('html', '<div id="id_functioncategory_content">');
        if (!empty($this->question->id) && $this->question->options->function) {
            $outputmanager->display_functionslist($this->question->options->function->programmedrespfcatid);
        }
        $mform->addElement('html', '</div>');

        // Link to add a function
        if ($caneditfunctions) {
            $addfunctionsurl = $CFG->wwwroot . '/question/type/programmedresp/manage.php?action=addfunctions';

            // If it's a function edition we should add the selected category id
            if (!empty($this->question->id) && $this->question->options->function) {
                $addfunctionsurl .= '&fcatid=' . $this->question->options->function->programmedrespfcatid;
            }

            $onclick = "window.open(this.href, this.target, 'menubar=0,location=0,scrollbars,resizable,width=650,height=600', true);return false;";
            $functionlink = '<a href="' . $addfunctionsurl . '" onclick="' . $onclick . '" target="addfunctions" id="id_addfunctionurl">' . get_string('addfunction', 'qtype_programmedresp') . '</a>';
            $mform->addElement('html', '<div class="fitem"><div class="fitemtitle"></div><div class="felement">' . $functionlink . '<br/><br/></div></div>');
        }

        // Arguments
        $mform->addElement('html', '<div id="id_programmedrespfid_content">');
        if (!empty($this->question->id) && $this->question->options->function) {
            $outputmanager->display_args($this->question->options->function->id,
                    $this->question->questiontext, $this->question->options->args,
                    $this->question->options->vars, $this->quizid);
        }
        $mform->addElement('html', '</div>');


        // Tolerance
        $tolerancetypes = array(
            PROGRAMMEDRESP_TOLERANCE_NOMINAL => get_string(
                    'tolerancenominal', 'qtype_programmedresp'
            ),
            PROGRAMMEDRESP_TOLERANCE_RELATIVE => get_string(
                    'tolerancerelative', 'qtype_programmedresp'
            ),
        );
        $mform->addElement('header', 'toleranceheader',
                get_string("tolerance", "qtype_programmedresp"));
        $mform->addElement('select', 'tolerancetype',
                get_string("tolerancetype", "qtype_programmedresp"), $tolerancetypes);
        $mform->addElement('text', 'tolerance',
                get_string("tolerance", "qtype_programmedresp"));

        $mform->addRule('tolerance', null, 'required', null, 'client');
        $mform->addRule('tolerance', null, 'numeric', null, 'client');
        $mform->setType('tolerance', PARAM_NUMBER);

//        $mform->removeElement('generalfeedback');
//        $mform->addElement('hidden', 'generalfeedback');
//        $mform->setType('generalfeedback', PARAM_RAW);

        // Interactive settings such as penalty fields
        $this->add_interactive_settings();

        // Add the onload javascript to hide next steps
        if (empty($this->question->id)) {
            $PAGE->requires->js('/question/type/programmedresp/onload.js');
        }
    }

    /**
     * Perform an preprocessing needed on the data passed to {@link set_data()}
     * before it is used to initialise the form.
     * @param object $question the data being passed to the form.
     * @return object $question the modified data.
     */
    protected function data_preprocessing($question) {
        $question = $this->data_preprocessing_hints($question);
        if (!empty($question->id)) {
            // Variables
            $vars = programmedresp_preprocess_vars($question->options->vars);
            $question = (object) array_merge((array) $question, (array) $vars);

            // Function
            $question->functioncategory = $question->options->function->programmedrespfcatid;
            $question->programmedrespfid = $question->options->function->id;

            // Function responses
            foreach ($question->options->responses as $returnkey => $resp) {
                $fieldname = 'resp_' . $returnkey;
                $question->{$fieldname} = $resp->label;
            }
        }

        return $question;
    }

    public function qtype() {
        return 'programmedresp';
    }

}
