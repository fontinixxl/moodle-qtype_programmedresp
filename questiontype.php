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
 * @copyright  2016 Gerard Cuello (gerard.urv@gmail.com)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/programmedresp/question.php');
// We're gonna need this file for qtype_numerical_answer_processor class.
require_once($CFG->dirroot . '/question/type/numerical/questiontype.php');

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

        $firstload = parent::get_question_options($question);
        if (false === $firstload) {
            return false;
        }

        $question->options->vars = $DB->get_records('qtype_programmedresp_var',
                array('question' => $question->id));
        $question->options->concatvars = $DB->get_records('qtype_programmedresp_conc',
                array('question' => $question->id));
        $question->options->function = $DB->get_record('qtype_programmedresp_f',
            array('id' => $question->options->programmedrespfid));

        if (!empty($question->options->function)) {
            $question->options->args = $DB->get_records('qtype_programmedresp_arg',
                    array('question' => $question->id), 'argkey ASC', 'argkey, id, origin, type, value');
            $question->options->responses = $DB->get_records('qtype_programmedresp_resp',
                    array('question' => $question->id), 'returnkey ASC', 'returnkey, label');
        }

        return true;
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
        $question->ap = new qtype_numerical_answer_processor(array());
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
         * Once we know there are params, they will be cleaned propertly so it is secure anyway.
         */
        foreach ($_POST as $varname => $value) {
            // Insert var
            if (substr($varname, 0, 4) == 'var_') {
                $vardata = explode('_', $varname);
                empty($vars[$vardata[2]]) && $vars[$vardata[2]] = new stdClass();
                $vars[$vardata[2]]->{$vardata[1]} = clean_param($value, PARAM_FLOAT);   // integer or float
                // Insert a function argument
                // argtype_0 => '0' - where the value is the argument's type (linker, var, concat)
            } else if (substr($varname, 0, 8) == 'argtype_') {
                $argobj = new stdClass();
                $argobj->question = $question->id;
                // Store the arg number.
                $argobj->argkey = intval(substr($varname, 8));  // integer

                // Assignem procedencia de l'argument
                $argobj->origin = (PROGRAMMEDRESP_ARG_LINKER == intval($value))
                    ? 'linker' : 'local';

                $argname = $argtypesmapping[intval($value)] . "_" . $argobj->argkey;
                // $argvalue contains the id of the selected var.
                $argvalue = optional_param($argname, false, PARAM_ALPHANUMEXT);

                // Si l'argument es linker i no existeix cap variable, vol dir que o be
                // estem guardant del question bank o be que no hi ha variables linker.
                // En tot cas, no hem de guardar ni type ni valor
                if ($argvalue) {
                    $argobj->type = intval($value);
                    $argobj->value = clean_param($argvalue, PARAM_TEXT);  // integer or float if it's fixed or a varname
                } else {
                    $argobj->type = NULL;
                    $argobj->value = '';
                }

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
            // TODO: Delete all unused vars from DB:
            // If varnames has changed, we need to remove the old ones.
            // CAUTION!! These vars can be already assigned to some function argument
        }
        // If there are previous concat vars, delete the non used.
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
                if ($arg->origin == 'local' && $arg->type == PROGRAMMEDRESP_ARG_VARIABLE) {
                    if (!isset($vars[$arg->value])) {
                        print_error('errorcantfindvar', 'qtype_programmedresp', $arg->value);
                    }
                    $arg->value = $vars[$arg->value]->id;
                }

                // If it's a concat var we must serialize the concatvar_N param
                if ($arg->origin == 'local' &&  $arg->type == PROGRAMMEDRESP_ARG_CONCAT) {

                    if (!isset($concatvars[$arg->value])) {
                        print_error('errorcantfindvar', 'qtype_programmedresp', $arg->value);
                    }
                    $arg->value = $concatvars[$arg->value]->id;
                }
                // The linkervardata is like this: {var|concat}_varid
                // En el cas que crem/editem una pregunta des del question bank, no hem d'assignar
                // type ni value, ja que no en tindrà.
                if ($arg->origin == 'linker' && !empty($arg->value)) {
                    // Obtenim array on el primer index (0) conté el tipus de variable i el
                    // segon (1) conté el id de la variable.
                    $linkervardata = explode('_', $arg->value);
                    // Intercanviem clau (int identifica el tipus de variable)
                    // per valor (string tipus de variable). Això ho fem per obtenir directe
                    // el int que identifica el tipus de variable (concat o var).
                    $argtypeflipped = array_flip($argtypesmapping);
                    // Ens quedem el integer que identifica el tipus de variable.
                    $arg->type = $argtypeflipped[$linkervardata[0]];
                    $arg->value = $linkervardata[1]; // get only the id of the var.
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

                // If it's a linkerdesc it must be stored with thmpte selected var
//                if (!empty($linkervararg[$argkey]) && $arg->type == PROGRAMMEDRESP_ARG_LINKER) {
//                    $linkervararg[$argkey]->programmedrespargid = $arg->id;
//
//                    if ($linkervararg[$argkey]->id = $DB->get_field('qtype_programmedresp_v_arg', 'id', array(
//                        'quizid' => $question->quizid, 'programmedrespargid' => $arg->id))) {
//                        $DB->update_record('qtype_programmedresp_v_arg', $linkervararg[$argkey]);
//                    } else {
//                        $DB->insert_record('qtype_programmedresp_v_arg', $linkervararg[$argkey]);
//                    }
//                }
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
