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
 * @copyright  THEYEAR Gerard Cuello (YOURCONTACTINFO)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/edit_question_form.php');
require_once($CFG->dirroot . '/question/type/programmedresp/lib.php');
require_once($CFG->dirroot . '/question/type/programmedresp/programmedresp_output.class.php');

/**
 * programmedresp question editing form definition.
 *
 * @copyright  2013 Gerard Cuello (gerard.urv@estudiants.urv.cat)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_programmedresp_edit_form extends question_edit_form {

    protected function definition_inner($mform) {
        global $CFG, $DB, $PAGE;

        //GERARD
        $id = required_param('cmid', PARAM_INT);


        $caneditfunctions = has_capability('moodle/question:config', get_context_instance(CONTEXT_SYSTEM));


        // To lower than 1.9.9
        $PAGE->requires->js('/question/type/programmedresp/script.js');

        // Adding wwwroot
        echo "<script type=\"text/javascript\">//<![CDATA[\n" .
        "this.wwwroot = '" . $CFG->wwwroot . "';\n" .
        "//]]></script>\n";

        // Data
        $categories = $DB->get_records('qtype_programmedresp_fcat', array(), 'id ASC', 'id, parent, name');
        echo "<br>Editing form: question->id = " . $this->question->id;
        echo '<br> intenta recuperar el contingut de:  ' . $CFG->dataroot . '/qtype_programmedresp.php';
        // If there are previous data
        if (!empty($this->question->id)) {
            $this->programmedresp = $DB->get_record('qtype_programmedresp', array('question' => $this->question->id));
            $this->programmedresp_f = $DB->get_record('qtype_programmedresp_f', array('id' => $this->programmedresp->programmedrespfid));
            $this->programmedresp_vars = $DB->get_records('qtype_programmedresp_var', array('programmedrespid' => $this->programmedresp->id));
            $this->programmedresp_args = $DB->get_records('qtype_programmedresp_arg', array('programmedrespid' => $this->programmedresp->id), '', 'argkey, type, value');
            $this->programmedresp_resps = $DB->get_records('qtype_programmedresp_resp', array('programmedrespid' => $this->programmedresp->id), 'returnkey ASC', 'returnkey, label');
        }

        if (isset($this->programmedresp_args)) {
            echo "<br> els arguments si existeixen";
        }

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

        $tolerancetypes = array(PROGRAMMEDRESP_TOLERANCE_NOMINAL => get_string('tolerancenominal', 'qtype_programmedresp'),
            PROGRAMMEDRESP_TOLERANCE_RELATIVE => get_string('tolerancerelative', 'qtype_programmedresp'));

        // Form elements
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
        //echo "<br> questiontext: ".$this->question->questiontext;
        if (!empty($this->question->id)) {
            $outputmanager->display_vars($this->question->questiontext, $this->programmedresp_args);
        }
        $mform->addElement('html', '</div>');

        // Functions header
        $mform->addElement('header', 'functionheader', get_string("assignfunction", "qtype_programmedresp"));

        // Category select
        $catattrs['onchange'] = 'update_addfunctionurl();return display_functionslist(this);';
        $mform->addElement('select', 'functioncategory', get_string('functioncategory', 'qtype_programmedresp'), $catoptions, $catattrs);

        // Dirty hack to add the function (added later through ajax)
        if (empty($this->question->id)) {
            $mform->addElement('hidden', 'programmedrespfid');
        }

        // Link to add a category
        if ($caneditfunctions) {
            $addcategoryurl = $CFG->wwwroot . '/question/type/programmedresp/manage.php?action=addcategory&id=' . $id;
            $onclick = "window.open(this.href, this.target, 'menubar=0,location=0,scrollbars,resizable,width=500,height=600', true);return false;";
            $categorylink = '<a href="' . $addcategoryurl . '" onclick="' . $onclick . '" target="addcategory">' . get_string('addcategory', 'qtype_programmedresp') . '</a>';
            $mform->addElement('html', '<div class="fitem"><div class="fitemtitle"></div><div class="felement">' . $categorylink . '<br/><br/></div></div>');
        }

        // Function list
        $mform->addElement('html', '<div id="id_functioncategory_content">');
        if (!empty($this->question->id)) {
            $outputmanager->display_functionslist($this->programmedresp_f->programmedrespfcatid);
        }
        $mform->addElement('html', '</div>');

        // Link to add a function
        if ($caneditfunctions) {
            $addfunctionsurl = $CFG->wwwroot . '/question/type/programmedresp/manage.php?action=addfunctions';

            // If it's a function edition we should add the selected category id
            if (!empty($this->question->id)) {
                $addfunctionsurl .= '&fcatid=' . $this->programmedresp_f->programmedrespfcatid;
            }

            $onclick = "window.open(this.href, this.target, 'menubar=0,location=0,scrollbars,resizable,width=650,height=600', true);return false;";
            $functionlink = '<a href="' . $addfunctionsurl . '" onclick="' . $onclick . '" target="addfunctions" id="id_addfunctionurl">' . get_string('addfunction', 'qtype_programmedresp') . '</a>';
            $mform->addElement('html', '<div class="fitem"><div class="fitemtitle"></div><div class="felement">' . $functionlink . '<br/><br/></div></div>');
        }

        // Arguments
        $mform->addElement('html', '<div id="id_programmedrespfid_content">');
        if (!empty($this->question->id)) {
            $outputmanager->display_args($this->programmedresp_f->id, $this->question->questiontext, $this->programmedresp_args, $this->programmedresp_vars);
        }
        $mform->addElement('html', '</div>');

        // Tolerance
        $mform->addElement('header', 'toleranceheader', get_string("tolerance", "qtype_programmedresp"));
        $mform->addElement('select', 'tolerancetype', get_string("tolerancetype", "qtype_programmedresp"), $tolerancetypes);
        $mform->addElement('text', 'tolerance', get_string("tolerance", "qtype_programmedresp"));
        $mform->addRule('tolerance', null, 'required', null, 'client');
        $mform->addRule('tolerance', null, 'numeric', null, 'client');
        $mform->setType('tolerance', PARAM_NUMBER);

        // Add the onload javascript to hide next steps
        if (empty($this->question->id)) {
            $PAGE->requires->js('/question/type/programmedresp/onload.js');
        }
    }

    public function set_data($question) {
        //parent::set_data($question);
        echo '<br>In function set_data()';
        //echo 'print question object : '.$question;
        if (!empty($question->id)) {

            // Variables
            $varfields = programmedresp_get_var_fields();
            if ($this->programmedresp_vars) {
                echo '<br>foreach var..';
                foreach ($this->programmedresp_vars as $var) {
                    foreach ($varfields as $varfield => $fielddesc) {
                        $fieldname = 'var_' . $varfield . '_' . $var->varname;
                        echo($fieldname . '<br>');
                        $question->{$fieldname} = $var->{$varfield};
                    }
                }
            }

            // Function and function category
            $question->functioncategory = $this->programmedresp_f->programmedrespfcatid;
            $question->programmedrespfid = $this->programmedresp_f->id;

            // Function responses
            foreach ($this->programmedresp_resps as $returnkey => $resp) {
                $fieldname = 'resp_' . $returnkey;
                $question->{$fieldname} = $resp->label;
            }

            // Tolerance
            $programmedresp = array('tolerancetype', 'tolerance');
            foreach ($programmedresp as $field) {
                $question->{$field} = $question->options->programmedresp->{$field};
            }
        }

        parent::set_data($question);
    }

    /* protected function data_preprocessing($question) {
      $question = parent::data_preprocessing($question);
      $question = $this->data_preprocessing_hints($question);

      return $question;
      } */

    public function qtype() {
        return 'programmedresp';
    }

}
