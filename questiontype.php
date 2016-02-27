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
 * Question type class for the programmedresp question type.
 *
 * @package    qtype
 * @subpackage programmedresp
 * @copyright 2016 Gerard Cuello (gerard.urv@gmail.com)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/programmedresp/question.php');

/**
 * The programmedresp question type.
 *
 * @copyright 2016 Gerard Cuello (gerard.urv@gmail.com)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_programmedresp extends question_type {

    public function extra_question_fields() {
        return array('qtype_programmedresp', 'id', 'programmedrespfid', 'tolerancetype', 'tolerance');
    }

    function questionid_column_name() {
        return 'question';
    }

    /**
     * Loads the question type specific options for the question
     * This information is placed in the $question->option field.
     * It's call once we click on question to edit it.
     * 
     * @param object $question The question object for the question. 
     * 		  This object should be updated to include the question type
     *        specific information (it is passed by reference).
     */
    public function get_question_options($question) {
        global $DB;
        parent::get_question_options($question);
        // Change $question->options->id (id of programmedresp table) obtained by extra_question_fields()
        $question->options->programmedrespid = $question->options->id;
        unset($question->options->id);
        // Store question vars from this question (local). 
        $question->options->vars['programmed'] = $DB->get_records('qtype_programmedresp_var', array('question' => $question->id));
        // Get all function categories
        $question->options->function = $DB->get_record('qtype_programmedresp_f', array('id' => $question->options->programmedrespfid));
        $question->options->args = $DB->get_records('qtype_programmedresp_arg', array('programmedrespid' => $question->options->programmedrespid), '', 'argkey, type, value');
        $question->options->responses = $DB->get_records('qtype_programmedresp_resp', array('programmedrespid' => $question->options->programmedrespid), 'returnkey ASC', 'returnkey, label');


        $qtype = question_bank::get_qtype('linkerdescription', false);
        if (get_class($qtype) == 'qtype_missingtype') {
            // Mostrar alert o alguna notificació per indicar que no es podran 
            // utilitzar variables linkerdescription
//            var_dump("Qtype linkerdescription doen't exit!!");
            return;
        }

        // cmid is needed to get the quiz
        $cmid = optional_param('cmid', 0, PARAM_INT);
        if (!$cmid) {
            return true;
        }
        // TODO: Ensure that this function always return a quiz
        list($quiz, $unused) = get_module_from_cmid($cmid);
        // get the qtype_linkerdescription questions in this particular quiz
        // questions in quiz
        $qinquiz = $DB->get_records('qtype_linkerdescription', array('quiz' => $quiz->id), null, 'question');
        if (!$qinquiz) {
            return;
        }
        $question->options->vars['linker'] = $DB->get_records_list('qtype_programmedresp_var', 'question', array_keys($qinquiz));

//        print_object($question->options->vars);
    }

    /**
     * Saves question-type specific options
     *
     * This is called by {@link save_question()} to save the question-type specific data
     * @return object $result->error or $result->notice
     * @param object $question  This holds the information from the editing form,
     *      it is not a standard question object.
     */
    public function save_question_options($question) {
        global $DB;
//        print_object(__CLASS__." ".__FUNCTION__);
        parent::save_question_options($question);
//      $this->save_hints($question);
        $programmedresp = $DB->get_record('qtype_programmedresp', array('question' => $question->id));
        // If we are updating, they will be reinserted
        $DB->delete_records('qtype_programmedresp_resp', array('programmedrespid' => $programmedresp->id));
        //$argtypesmapping = programmedresp_get_argtypes_mapping();
        /*
         * Params are getting directly from POST because we don't know the field names
         * They are generated by AJAX in edit_question_form. (question vars and arguments).
         * Once we know there are params, they will be cleaned propertly.
         */
        $argtypesmapping = programmedresp_get_argtypes_mapping();
        $i = 0;
        $vars = array();
        $args = array();
        foreach ($_POST as $varname => $value) {
            // Insert var
            if (substr($varname, 0, 4) == 'var_') {
                $vardata = explode('_', $varname);
                $vars[$vardata[2]]->{$vardata[1]} = clean_param($value, PARAM_FLOAT);   // integer or float
                // Insert a function argument
            } else if (substr($varname, 0, 8) == 'argtype_') {

                $args[$i]->programmedrespid = $programmedresp->id;
                $args[$i]->argkey = intval(substr($varname, 8));  // integer
                $args[$i]->type = intval($value);
//                print_object($args[$i]);
                // There are a form element for each var type (fixed, variable, concat, linkerdescription)
                // $argvalue contains the value of the selected element
                // TODO: For guidedquiz, value is empty
                $argvalue = $_POST[$argtypesmapping[intval($value)] . "_" . $args[$i]->argkey];
                $args[$i]->value = clean_param($argvalue, PARAM_TEXT);  // integer or float if it's fixed or a varname

                $i++;
                // Insert a function response
            } else if (substr($varname, 0, 5) == 'resp_') {
                $resp->programmedrespid = $programmedresp->id;
                $resp->returnkey = intval(substr($varname, 5));   // $varname must be something like resp_0
                $resp->label = clean_param($value, PARAM_TEXT);
                if (!$DB->insert_record('qtype_programmedresp_resp', $resp)) {
                    print_error('errordb', 'qtype_programmedresp');
                }
            }
        }
        // Delete any left over old answer records.
        if (!empty($vars)) {
            foreach ($vars as $varname => $var) {
                $var->question = $question->id;
                $var->varname = $varname;

                // Update
                if ($var->id = $DB->get_field('qtype_programmedresp_var', 'id', array('question' => $var->question, 'varname' => $var->varname))) {

                    if (!$DB->update_record('qtype_programmedresp_var', $var)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }

                    // Insert
                } else {
                    if (!$vars[$varname]->id = $DB->insert_record('qtype_programmedresp_var', $var)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }
                }
            }
            // TODO: If varnames are changes, we need to remove the old ones. If not they ara still in database!!
            // METODE DE LES 7 DIFERENCIES !!
        }

        if ($args) {
            foreach ($args as $arg) {
                // If it's a variable we must look for the qtype_programmedresp_var identifier
                if ($arg->type == PROGRAMMEDRESP_ARG_VARIABLE) {
                    if (!isset($vars[$arg->value])) {
                        print_error('errorcantfindvar', 'qtype_programmedresp', $arg->value);
                    }
                    $arg->value = $vars[$arg->value]->id;
                }

                // If it's a concat var we must serialize the concatvar_N param
                if ($arg->type == PROGRAMMEDRESP_ARG_CONCAT) {

                    $concatnum = intval(substr($arg->value, 10));
                    if (!$concatvalues = optional_param('concatvar_' . $concatnum, false, PARAM_ALPHANUM)) {
                        print_error('errorcantfindvar', 'qtype_programmedresp', $arg->value);
                    } else {
                        if (!$concreadablename = optional_param('nconcatvar_' . $concatnum, false, PARAM_ALPHANUM)) {
                            print_error('errorcantfindvar', 'qtype_programmedresp', $arg->value);
                        }
                    }

                    // Inserting/Updating the new concat var
                    $concatvarname = 'concatvar_' . $concatnum;
                    if (!$concatobj = $DB->get_record('qtype_programmedresp_conc', array('origin' => 'question', 'instanceid' => $programmedresp->id, 'name' => $concatvarname))) {
                        $concatobj = new stdClass();
                        $concatobj->origin = 'question';
                        $concatobj->instanceid = $programmedresp->id;
                        $concatobj->name = $concatvarname;
                        $concatobj->readablename = $concreadablename;
                        $concatobj->vars = programmedresp_serialize($concatvalues);
                        if (!$concatobj->id = $DB->insert_record('qtype_programmedresp_conc', $concatobj)) {
                            print_error('errordb', 'qtype_programmedresp');
                        }
                    } else {
                        $concatobj->vars = programmedresp_serialize($concatvalues);
                        $DB->update_record('qtype_programmedresp_conc', $concatobj);
                    }

                    $arg->value = $concatobj->id;
                }
                
                // TODO: add option for PROGRAMMEDRESP_LINKER (old guiedquiz)
                
                // Update
                if ($arg->id = $DB->get_field('qtype_programmedresp_arg', 'id', array('programmedrespid' => $arg->programmedrespid, 'argkey' => $arg->argkey))) {

                    if (!$DB->update_record('qtype_programmedresp_arg', $arg)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }

                    // Insert
                } else {
                    if (!$DB->insert_record('qtype_programmedresp_arg', $arg)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }
                }
            }
        }
        return true;
    }

    public function get_random_guess_score($questiondata) {
        // TODO.
        return 0;
    }

    public function get_possible_responses($questiondata) {
        // TODO.
        return array();
    }

    public function move_files($questionid, $oldcontextid, $newcontextid) {
        parent::move_files($questionid, $oldcontextid, $newcontextid);
        $this->move_files_in_hints($questionid, $oldcontextid, $newcontextid);
    }

    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }

}
