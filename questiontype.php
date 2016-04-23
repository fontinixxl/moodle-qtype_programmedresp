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
        return array('qtype_programmedresp', 'programmedrespfid', 'tolerancetype', 'tolerance');
    }

    // TODO: delete this method because question_type already has defined it.
    //  It suposes rename the primary key to 'questionid'
    function questionid_column_name() {
        return 'question';
    }

    /**
     * Set any missing settings for this question to the default values. This is
     * called before displaying the question editing form.
     *
     * @param object $questiondata the question data, loaded from the databsae,
     *      or more likely a newly created question object that is only partially
     *      initialised.
     */
    function set_default_options($questiondata) {
        // I'm not sure if this method can be usefull..
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
        $question->options->programmedresp = $DB->get_record('qtype_programmedresp',
                array('question' => $question->id));
        if (!$question->options->programmedresp) {
            return false;
        }
        //TODO: create $question->options object as new stdClass() ???
        $question->options->vars = $DB->get_records('qtype_programmedresp_var',
                array('question' => $question->id));
        $question->options->concatvars = $DB->get_records('qtype_programmedresp_conc',
                array('question' => $question->id));
        $question->options->args = $DB->get_records('qtype_programmedresp_arg',
                array('question' => $question->id), '', 'argkey, id, type, value');
        $question->options->responses = $DB->get_records('qtype_programmedresp_resp',
                array('question' => $question->id), 'returnkey ASC', 'returnkey, label');
        $question->options->function = $DB->get_record('qtype_programmedresp_f',
                array('id' => $question->options->programmedrespfid));
    }

    /**
     * Initialise the common question_definition fields.
     * @param question_definition $question the question_definition we are creating.
     * @param object $questiondata the question data loaded from the database.
     */
    protected function initialise_question_instance(\question_definition $question, $questiondata) {
        parent::initialise_question_instance($question, $questiondata);
        $question->vars = ($questiondata->options->vars) ? $questiondata->options->vars : array();
        // Only programmedresp qtype
        $question->concatvars = ($questiondata->options->concatvars) ? $questiondata->options->concatvars : array();
        $question->args = $questiondata->options->args;
        $question->function = $questiondata->options->function;
        // Response options
        $question->respfields = $questiondata->options->responses;
    }

    /**
     * TODO: refactor
     * Saves question-type specific options
     *
     * This is called by {@link save_question()} to save the question-type specific data
     * @return object $result->error or $result->notice
     * @param object $question  This holds the information from the editing form,
     *      it is not a standard question object.
     */
    public function save_question_options($question) {
        global $DB;
        parent::save_question_options($question);
        $programmedresp = $DB->get_record('qtype_programmedresp',
                array('question' => $question->id));

        // If we are updating, they will be reinserted
        if (!empty($programmedresp->id)) {
            $DB->delete_records('qtype_programmedresp_resp', array('question' => $question->id));
        }
        $argtypesmapping = programmedresp_get_argtypes_mapping();
        $i = 0;
        $vars = $args = $concatvars = array();
        /*
         * Params are getting directly from POST because we don't know the field names
         * They are generated by AJAX in edit_question_form. (question vars and arguments).
         * Once we know there are params, they will be cleaned propertly.
         */
        foreach ($_POST as $varname => $value) {
            // Insert var
            if (substr($varname, 0, 4) == 'var_') {
                $vardata = explode('_', $varname);
                empty($vars[$vardata[2]]) && $vars[$vardata[2]] = new stdClass();
                $vars[$vardata[2]]->{$vardata[1]} = clean_param($value, PARAM_FLOAT);   // integer or float
                // Insert a function argument
            } else if (substr($varname, 0, 8) == 'argtype_') {
                $argobj = new stdClass();
                $argobj->question = $question->id;
                $argobj->argkey = intval(substr($varname, 8));  // integer
                $argobj->type = intval($value);

                // There are a form element for each var type (fixed, variable, concat, linkerdesc)
                // $argvalue contains the value of the selected var.
                $argname = $argtypesmapping[intval($value)] . "_" . $argobj->argkey;
                $argvalue = optional_param($argname, false, PARAM_ALPHANUMEXT);
                $argobj->value = clean_param($argvalue, PARAM_TEXT);  // integer or float if it's fixed or a varname

                $args[$i] = $argobj;
                $i++;

            // Inserting/Updating the new concat var
            } else if (substr($varname, 0, 10) == 'concatvar_') {
                $concatnum = intval(substr($varname, 10));
                if (!$concatvalues = optional_param_array('concatvar_' . $concatnum, false, PARAM_ALPHANUM)) {
                        print_error('errorcantfindvar', 'qtype_programmedresp', $varname);
                }
                $concatobj = new stdClass();
                $concatobj->readablename = required_param('nconcatvar_' . $concatnum, PARAM_ALPHAEXT);
                $concatobj->vars = programmedresp_serialize($concatvalues);

                $concatvars['concatvar_' . $concatnum] = $concatobj;
                // Insert a function response
            } else if (substr($varname, 0, 5) == 'resp_') {
                $resp = new stdClass();
                $resp->question = $question->id;
                $resp->returnkey = intval(substr($varname, 5));   // $varname must be something like resp_0
                $resp->label = clean_param($value, PARAM_TEXT);
                if (!$DB->insert_record('qtype_programmedresp_resp', $resp)) {
                    print_error('errordb', 'qtype_programmedresp');
                }

            // Store selected linker vars for the $argindex argument
            } else if (substr($varname, 0, 7) == 'linker_') {
                $linkervardata = explode('_', clean_param($value, PARAM_ALPHANUMEXT));
                $argindex = intval(substr($varname, -1));

                $linkerobj = new stdClass();
                $linkerobj->quizid = $question->quizid;
                $linkerobj->type = $linkervardata[0]; //var or concatvar
                $linkerobj->instanceid = $linkervardata[1];

                $linkervararg[$argindex] = $linkerobj;
            }
        }

        // Delete any left over old answer records.
        if (!empty($vars)) {
            foreach ($vars as $varname => $var) {
                $var->question = $question->id;
                $var->varname = $varname;

                // Update
                if ($var->id = $DB->get_field('qtype_programmedresp_var', 'id',
                        array('question' => $var->question, 'varname' => $var->varname))) {

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
            // TODO: Delete from DB all unused vars:
            // If varnames are changes, we need to remove the old ones.
            // CAUTION!! These vars has already been assigned to any function argument
        }
        // If there are previous concat vars delete the non used ones.
        $oldconcatvars = $DB->get_records('qtype_programmedresp_conc', array('question' => $question->id),'id', 'id, name');
        if ($oldconcatvars) {
            foreach ($oldconcatvars as $oldconcatvar) {
                if (empty($concatvars[$oldconcatvar->name])) {
                    $DB->delete_records('qtype_programmedresp_conc', array('id' => $oldconcatvar->id));
                }
            }
        }
        if (!empty($concatvars)) {
            foreach ($concatvars as $concatname => $concatvar) {
                $concatvar->name = $concatname;
                $concatvar->question = $question->id;

                // Update
                if ($concatvar->id = $DB->get_field('qtype_programmedresp_conc', 'id',
                        array('question' => $question->id, 'name' => $concatname))) {
                    if (!$DB->update_record('qtype_programmedresp_conc', $concatvar)) {
                        print_error('errordb', 'qtype_programmedresp');
                   }
                } else {
                    if (!$concatvars[$concatname]->id = $DB->insert_record('qtype_programmedresp_conc', $concatvar)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }
                }
            }
        }

        if ($args) {
            foreach ($args as $argkey => $arg) {
                // If it's a variable we must look for the qtype_programmedresp_var identifier
                if ($arg->type == PROGRAMMEDRESP_ARG_VARIABLE) {
                    if (!isset($vars[$arg->value])) {
                        print_error('errorcantfindvar', 'qtype_programmedresp', $arg->value);
                    }
                    $arg->value = $vars[$arg->value]->id;
                }

                // If it's a concat var we must serialize the concatvar_N param
                if ($arg->type == PROGRAMMEDRESP_ARG_CONCAT) {

                    if (!isset($concatvars[$arg->value])) {
                        print_error('errorcantfindvar', 'qtype_programmedresp', $arg->value);
                    }
                    $arg->value = $concatvars[$arg->value]->id;
                }

                // Update
                if ($arg->id = $DB->get_field('qtype_programmedresp_arg', 'id',
                        array('question' => $arg->question, 'argkey' => $arg->argkey))) {

                    if (!$DB->update_record('qtype_programmedresp_arg', $arg)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }

                    // Insert
                } else {
                    if (!$arg->id = $DB->insert_record('qtype_programmedresp_arg', $arg, true)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }
                }

                // If it's a linkerdesc qtype it must be stored with the selected var
                if (!empty($linkervararg[$argkey]) && $arg->type == PROGRAMMEDRESP_ARG_LINKER) {
                    $linkervararg[$argkey]->programmedrespargid = $arg->id;

                    if ($linkervararg[$argkey]->id = $DB->get_field('qtype_programmedresp_v_arg', 'id', array(
                        'quizid' => $question->quizid, 'programmedrespargid' => $arg->id))) {
                        $DB->update_record('qtype_programmedresp_v_arg', $linkervararg[$argkey]);
                    } else {
                        $DB->insert_record('qtype_programmedresp_v_arg', $linkervararg[$argkey]);
                    }
                }
            }
        }

        $this->save_hints($question);

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

    public function delete_question($questionid, $contextid) {
        global $DB;

        if (!$programmedresp = $DB->get_record('qtype_programmedresp',
                array('question' => $questionid))) {
            return false;
        }

        // Delete all argument associations with global vars (linkerdesc).
        if ($args = $DB->get_records('qtype_programmedresp_arg', array(
            'question' => $questionid, 'type' => PROGRAMMEDRESP_ARG_LINKER))) {
            foreach ($args as $arg) {
                $DB->delete_records('qtype_programmedresp_v_arg', array('programmedrespargid' => $arg->id));
            }
        }
        // Delete args
        $DB->delete_records('qtype_programmedresp_arg', array('question' => $questionid));
        // Delete responses
        $DB->delete_records('qtype_programmedresp_resp', array('question' => $questionid));

        $vars = $DB->get_records('qtype_programmedresp_var', array('question' => $questionid));
        if ($vars) {
            foreach ($vars as $var) {
                // Delete all random values for each variable.
                $DB->delete_records('qtype_programmedresp_val', array('varid' => $var->id));
            }
            // Delete variables.
            $DB->delete_records('qtype_programmedresp_var', array('question' => $questionid));

            // Delete concatenated vars.
            $DB->delete_records('qtype_programmedresp_conc', array('question' => $questionid));

        }

        // Parent method will be delete the extra question table.
        parent::delete_question($questionid, $contextid);
    }

    protected function delete_files($questionid, $contextid) {
        parent::delete_files($questionid, $contextid);
        $this->delete_files_in_hints($questionid, $contextid);
    }

}
