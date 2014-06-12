<?php

/**
 * The question type class for the Programmed response question type.
 *
 * @copyright 2010 David Monllaó <david.monllao@urv.cat>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package qtype_programmedresp
 */
/*
  require_once($CFG->libdir . '/questionlib.php');
  require_once($CFG->dirroot . '/question/engine/lib.php');
  require_once($CFG->dirroot . '/question/type/programmedresp/question.php');
 */
require_once($CFG->dirroot . '/question/type/programmedresp/lib.php');
programmedresp_check_datarootfile();
require_once($CFG->dataroot . '/qtype_programmedresp.php');

/**
 * The Programmed response question class
 *
 * The programmed question can include vars with format {$varname} inside the
 * question text, for each var it must be defined the number of values, the max and min limit
 * and the increment. Each question also chooses a function from a repository which
 * will return the response/s based on the vars values generated randomly for each different
 * quiz attempt.
 */
class qtype_programmedresp extends question_type {

    function name() {
        return 'programmedresp';
    }

    function extra_question_fields() {
        return array('qtype_programmedresp', 'programmedrespfid', 'tolerancetype', 'tolerance');
    }

    function questionid_column_name() {
        return 'question';
    }

    /**
     * Gets the programmedresp data
     *
     * @return boolean to indicate success of failure.
     */
    function get_question_options(&$question) {

        $question->options->programmedresp = get_record('qtype_programmedresp', 'question', $question->id);
        if (!$question->options->programmedresp) {
            return false;
        }
        $question->options->vars = get_records('qtype_programmedresp_var', 'programmedrespid', $question->options->programmedresp->id);
        $question->options->args = get_records('qtype_programmedresp_arg', 'programmedrespid', $question->options->programmedresp->id);
        $question->options->resps = get_records('qtype_programmedresp_resp', 'programmedrespid', $question->options->programmedresp->id, 'returnkey ASC', 'returnkey, label');
        $question->options->concatvars = get_records_select('qtype_programmedresp_conc', "origin = 'question' AND instanceid = '{$question->options->programmedresp->id}'");

        $question->options->function = get_record('qtype_programmedresp_f', 'id', $question->options->programmedresp->programmedrespfid);
        if (!$question->options->function) {
            return false;
        }
        return true;
    }

    /**
     * Save the units and the answers associated with this question.
     * @return boolean to indicate success of failure.
     */
    function save_question_options($question) {

        // It doesn't return the inserted/updated qtype_programmedresp->id
        parent::save_question_options($question);
        $programmedresp = get_record('qtype_programmedresp', 'question', $question->id);

        // If we are updating, they will be reinserted
        delete_records('qtype_programmedresp_resp', 'programmedrespid', $programmedresp->id);

        if (empty($question->vars) || empty($question->args)) {
            $result = $this->save_question_options_from_form($question, $programmedresp);
        } else {
            $result = $this->save_question_options_from_questiondata($question, $programmedresp);
        }

        // Rollback changes
        if (!$result) {
            $this->delete_question($question->id);
            return false;
        }

        return true;
    }

    /**
     * Gets the data to insert from the $question object (petitions from import...)
     * @param $question
     * @param $programmedresp
     */
    function save_question_options_from_questiondata($question, $programmedresp) {

        $varmap = array();   // Maintains the varname -> varid relation
//        if (empty($question->vars) || empty($question->args) || empty($question->resps)) {
//            return false;
//        }
        // Vars
        if (!empty($question->vars)) {
            foreach ($question->vars as $vardata) {

                $var->programmedrespid = $programmedresp->id;
                $var->varname = $vardata->varname;
                $var->nvalues = $vardata->nvalues;
                $var->maximum = $vardata->maximum;
                $var->minimum = $vardata->minimum;
                $var->valueincrement = $vardata->valueincrement;
                if (!$varmap[$vardata->varname] = insert_record('qtype_programmedresp_var', $var)) {
                    print_error('errordb', 'qtype_programmedresp');
                }
            }
        }

        // Concat vars
        if (!empty($question->concatvars)) {
            foreach ($question->concatvars as $vardata) {

                $var->origin = $vardata->name;
                $var->instanceid = $programmedresp->id;
                $var->name = $vardata->name;
                $var->vars = $vardata->vars;
                if (!$concatvarmap[$vardata->name] = insert_record('qtype_programmedresp_conc', $var)) {
                    print_error('errordb', 'qtype_programmedresp');
                }
            }
        }

        // Args
        if (!empty($question->args)) {
            foreach ($question->args as $argdata) {

                $arg->programmedrespid = $programmedresp->id;
                $arg->argkey = $argdata->argkey;
                $arg->type = $argdata->type;

                // Getting the var id
                if ($argdata->type == PROGRAMMEDRESP_ARG_VARIABLE) {
                    $argdata->value = $varmap[$argdata->value];

                    // Getting the concat var id
                } else if ($argdata->type == PROGRAMMEDRESP_ARG_CONCAT) {
                    $argdata->value = $concatvarmap[$argdata->value];
                }

                $arg->value = $argdata->value;
                if (!insert_record('qtype_programmedresp_arg', $arg)) {
                    print_error('errordb', 'qtype_programmedresp');
                }
            }
        }

        // Resps
        if (!empty($question->resps)) {
            foreach ($question->resps as $respdata) {

                $resp->programmedrespid = $programmedresp->id;
                $resp->returnkey = $respdata->returnkey;
                $resp->label = $respdata->label;
                if (!insert_record('qtype_programmedresp_resp', $resp)) {
                    print_error('errordb', 'qtype_programmedresp');
                }
            }
        }

        return true;
    }

    /**
     * Gets the data to insert/update from the _POST request
     * @param $question
     * @param $programmedresp
     */
    function save_question_options_from_form($question, $programmedresp) {

        $argtypesmapping = programmedresp_get_argtypes_mapping();

        // The arguments must be added after the vars
        $i = 0;

        foreach ($_POST as $varname => $value) {

            // Insert var
            if (substr($varname, 0, 4) == 'var_') {

                $vardata = explode('_', $varname);
                $vars[$vardata[2]]->{$vardata[1]} = clean_param($value, PARAM_NUMBER);   // integer or float
                // Insert a function argument
            } else if (substr($varname, 0, 8) == 'argtype_') {

                $args[$i]->programmedrespid = $programmedresp->id;
                $args[$i]->argkey = intval(substr($varname, 8));     // integer
                $args[$i]->type = intval($value);


                // There are a form element for each var type (fixed, variable, concat, guidedquiz)
                // $argvalue contains the value of the selected element
                $argvalue = $_POST[$argtypesmapping[intval($value)] . "_" . $args[$i]->argkey];
                $args[$i]->value = clean_param($argvalue, PARAM_TEXT);  // integer or float if it's fixed or a varname

                $i++;

                // Insert a function response
            } else if (substr($varname, 0, 5) == 'resp_') {

                $resp->programmedrespid = $programmedresp->id;
                $resp->returnkey = intval(substr($varname, 5));   // $varname must be something like resp_0
                $resp->label = clean_param($value, PARAM_TEXT);
                if (!insert_record('qtype_programmedresp_resp', $resp)) {
                    print_error('errordb', 'qtype_programmedresp');
                }
            }
        }

        // Inserting vars
//        if (!$vars || !$args) {
//            return false;
//        }

        if (!empty($vars)) {
            foreach ($vars as $varname => $var) {
                $var->programmedrespid = $programmedresp->id;
                $var->varname = $varname;

                // Update
                if ($var->id = get_field('qtype_programmedresp_var', 'id', 'programmedrespid', $var->programmedrespid, 'varname', $var->varname)) {

                    if (!update_record('qtype_programmedresp_var', $var)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }

                    // Insert
                } else {
                    if (!$vars[$varname]->id = insert_record('qtype_programmedresp_var', $var)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }
                }
            }
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
                    }

                    // Inserting/Updating the new concat var
                    $concatvarname = 'concatvar_' . $concatnum;
                    if (!$concatobj = get_record('qtype_programmedresp_conc', 'origin', 'question', 'instanceid', $programmedresp->id, 'name', $concatvarname)) {
                        $concatobj = new stdClass();
                        $concatobj->origin = 'question';
                        $concatobj->instanceid = $programmedresp->id;
                        $concatobj->name = $concatvarname;
                        $concatobj->vars = programmedresp_serialize($concatvalues);
                        if (!$concatobj->id = insert_record('qtype_programmedresp_conc', $concatobj)) {
                            print_error('errordb', 'qtype_programmedresp');
                        }
                    } else {
                        $concatobj->vars = programmedresp_serialize($concatvalues);
                        update_record('qtype_programmedresp_conc', $concatobj);
                    }

                    $arg->value = $concatobj->id;
                }

                // Update
                if ($arg->id = get_field('qtype_programmedresp_arg', 'id', 'programmedrespid', $arg->programmedrespid, 'argkey', $arg->argkey)) {

                    if (!update_record('qtype_programmedresp_arg', $arg)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }

                    // Insert
                } else {
                    if (!insert_record('qtype_programmedresp_arg', $arg)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }
                }
            }
        }

        return true;
    }

    /**
     * Deletes question from the question-type specific tables
     *
     * @param integer $questionid The question being deleted
     * @return boolean to indicate success of failure.
     */
    function delete_question($questionid) {

        $programmedresp = get_record('qtype_programmedresp', 'question', $questionid);
        if (!$programmedresp) {
            return false;
        }

        delete_records('qtype_programmedresp_arg', 'programmedrespid', $programmedresp->id);
        delete_records('qtype_programmedresp_resp', 'programmedrespid', $programmedresp->id);

        $vars = get_records('qtype_programmedresp_var', 'programmedrespid', $programmedresp->id);
        if ($vars) {
            foreach ($vars as $var) {
                delete_records('qtype_programmedresp_val', 'programmedrespvarid', $var->id);
            }
        }

        delete_records('qtype_programmedresp_var', 'programmedrespid', $programmedresp->id);
        delete_records('qtype_programmedresp_conc', 'origin', 'question', 'instanceid', $programmedresp->id);
        delete_records('qtype_programmedresp', 'question', $questionid);

        return true;
    }

    function create_session_and_responses(&$question, &$state, $cmoptions, $attempt) {
        // TODO create a blank repsonse in the $state->responses array, which
        // represents the situation before the student has made a response.
        return true;
    }

    function restore_session_and_responses(&$question, &$state) {

        // From 12|12 format of question_states->answer to array() with the return keys
        $responses = explode('|', $state->responses['']);

        unset($state->responses);
        foreach ($responses as $returnkey => $value) {
            $state->responses[$returnkey] = $value;
        }
        return true;
    }

    /**
     * Stores the user answer/s to question_states->answer with | separated
     *
     * @param $question
     * @param $state
     */
    function save_session_and_responses(&$question, &$state) {
        $responses = '';

        if (!empty($state->responses)) {
            $responses = implode('|', $state->responses);
        }

        return set_field('question_states', 'answer', $responses, 'id', $state->id);
    }

    function print_question_formulation_and_controls(&$question, &$state, $cmoptions, $options) {

        global $CFG;

//        if (!$state->attempt) {
//        	print_error('errorcantpreview', 'qtype_programmedresp');
//        }
        // Getting the module name from thispageurl
        $modname = programmedresp_get_modname();

        $programmedresp = get_record('qtype_programmedresp', 'question', $state->question);
        if (!$programmedresp) {
            return false;
        }

        $readonly = empty($options->readonly) ? '' : 'disabled="disabled"';

        // Print formulation
        $questiontext = $this->format_text($question->questiontext, $question->questiontextformat, $cmoptions);

        // Replacing vars for random values
        if (!empty($question->options->vars)) {
            foreach ($question->options->vars as $var) {

                // If this attempt doesn't have yet a value
                if (!$values = get_field('qtype_programmedresp_val', 'varvalues', 'attemptid', $state->attempt, 'programmedrespvarid', $var->id, 'module', $modname)) {

                    // Add a new random value
                    $values = $this->generate_value($state->attempt, $var, $modname);
                    if (is_null($values)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }
                }
                $values = programmedresp_unserialize($values);

                $valuetodisplay = implode(', ', $values);

                $questiontext = str_replace('{$' . $var->varname . '}', $valuetodisplay, $questiontext);
            }
        }

        // Executes the selected function and returns the correct response/s
        $correctresults = $this->get_correct_responses_without_round($question, $state);

        // If theres are responses we add them
        if (!empty($question->options->resps)) {
            foreach ($question->options->resps as $returnkey => $respdata) {

                if (!empty($state->responses[$returnkey])) {

                    // Adding the user answer
                    $question->options->resps[$returnkey]->value = $state->responses[$returnkey];

                    // The user answer is correct?
                    $fraction = $this->test_programmed_response($correctresults[$returnkey], $question->options->resps[$returnkey]->value, $programmedresp);
                    $question->options->resps[$returnkey]->class = question_get_feedback_class($fraction);
                    $question->options->resps[$returnkey]->feedbackimg = question_get_feedback_image($fraction);
                } else {  
                    $question->options->resps[$returnkey]->value = '';
                    $question->options->resps[$returnkey]->class = '';
                    $question->options->resps[$returnkey]->feedbackimg = '';
                }
            }
        }

        $image = get_question_image($question, $cmoptions->course);

        $feedback = '';
        if ($options->feedback) {
            
        }

        include("$CFG->dirroot/question/type/programmedresp/display.html");
    }

    /**
     * Generates a value based on $var attributes and inserts in into DB
     *
     * @param $attemptid
     * @param $var
     * @param $modname
     * @return null|string
     */
    function generate_value($attemptid, $var, $modname = false) {

        if (!$modname) {
            $modname = programmedresp_get_modname();
        }

        $programmedrespval->module = $modname;
        $programmedrespval->attemptid = $attemptid;
        $programmedrespval->programmedrespvarid = $var->id;
        $programmedrespval->varvalues = programmedresp_serialize(programmedresp_get_random_value($var));
        if (!insert_record('qtype_programmedresp_val', $programmedrespval)) {
            return null;
        }
        $values = $programmedrespval->varvalues;

        return $values;
    }

    /**
     * Sets the raw_grade.
     *
     * If there are more than one response it takes the average
     *
     * @param object $question
     * @param object $state
     * @param object $cmoptions
     * @return boolean
     */
    function grade_responses(&$question, &$state, $cmoptions) {
        global $CFG;

        // Get the function response data
        if (!$programmedresp = get_record('qtype_programmedresp', 'question', $state->question)) {
            return false;
        }

        // Executes the selected function and returns the correct response/s
        $correctresults = $this->get_correct_responses_without_round($question, $state);

        // Beginning with the max grade
        $state->raw_grade = $question->maxgrade;

        // Stores the average grade
        $fractions = array();
        $nresponses = 0;
        foreach ($correctresults as $resultkey => $correctresult) {

            if (empty($state->responses[$resultkey])) {
                $state->responses[$resultkey] = '';
            }
            // Compares the response against the correct result
            $fractions[] = $this->test_programmed_response($correctresult, $state->responses[$resultkey], $programmedresp);
            $nresponses++;
        }

        $state->raw_grade = array_sum($fractions) / $nresponses;

        // Make sure we don't assign negative or too high marks.
        $state->raw_grade = min(max((float) $state->raw_grade, 0.0), 1.0) * $question->maxgrade;

        // mark the state as graded
        $state->event = ($state->event == QUESTION_EVENTCLOSE) ? QUESTION_EVENTCLOSEANDGRADE : QUESTION_EVENTGRADE;

        return true;
    }

    /**
     * Gets a string to embed in a eval()
     *
     * @param object $arg
     * @param array $vars
     * @param integer $attemptid
     * @param integer $quizid
     * @return string
     */
    function get_exec_arg($arg, $vars, $attemptid, $quizid) {

        global $CFG;

        $modname = programmedresp_get_modname();

        switch ($arg->type) {

            case PROGRAMMEDRESP_ARG_FIXED:

                if (strstr($arg->value, ',')) {
                    $randomvalues = explode(',', $arg->value);
                } else {
                    $randomvalues = array($arg->value);
                }

                break;

            case PROGRAMMEDRESP_ARG_VARIABLE:

                $randomvalues = get_field('qtype_programmedresp_val', 'varvalues', 'attemptid', $attemptid, 'programmedrespvarid', $arg->value, 'module', $modname);

                // If the random value was not previously created let's create it (for example, answer a quiz where this question has not been shown)
                if (!$randomvalues) {

                    // Var data
                    $vardata = get_record('qtype_programmedresp_var', 'id', $arg->value);
                    $values = $this->generate_value($attemptid, $vardata);
                    if (is_null($values)) {
                        print_error('errornorandomvaluesdata', 'qtype_programmedresp');
                    }
                    $randomvalues = $values;
                }
                $randomvalues = programmedresp_unserialize($randomvalues);

                break;

            case PROGRAMMEDRESP_ARG_CONCAT:

                $concatdata = programmedresp_get_concatvar_data($arg->value);

                // To store the concatenated vars
                $randomvalues = array();

                // Getting the random param of each concat var
                foreach ($concatdata->values as $varname) {

                    // Getting the var id
                    foreach ($vars as $id => $vardata) {
                        if ($vardata->varname == $varname) {
                            $varid = $id;
                        }
                    }
                    if (empty($varid)) {
                        print_error('errorcantfindvar', 'qtype_programmedresp', $varname);
                    }

                    $random = get_field('qtype_programmedresp_val', 'varvalues', 'attemptid', $attemptid, 'programmedrespvarid', $varid, 'module', $modname);
                    if (!$random) {
                        print_error('errornorandomvaluesdata', 'qtype_programmedresp');
                    }
                    $randomvalues = array_merge($randomvalues, programmedresp_unserialize($random));
                }

                break;

            case PROGRAMMEDRESP_ARG_GUIDEDQUIZ:

                // Getting the argument variable
                $sql = "SELECT * FROM {$CFG->prefix}guidedquiz_var_arg gva
            	        WHERE gva.quizid = '$quizid' AND gva.programmedrespargid = '{$arg->id}'";

                if (!$vardata = get_record_sql($sql)) {
                    print_error('errorargumentnoassigned', 'qtype_programmedresp');
                }

                // To store the values
                $randomvalues = array();

                // A var
                if ($vardata->type == 'var') {
                    $random = get_field('guidedquiz_val', 'varvalues', 'guidedquizvarid', $vardata->instanceid, 'attemptid', $attemptid);
                    $randomvalues = programmedresp_unserialize($random);

                    // A concat var
                } else {

                    $var = get_record('qtype_programmedresp_conc', 'id', $vardata->instanceid);
                    if (!$var) {
                        print_error('errorargumentnoassigned', 'qtype_programmedresp');
                    }

                    // Adding each concatenated variable to $randomvalues
                    $varnames = programmedresp_unserialize($var->vars);
                    foreach ($varnames as $varname) {

                        // Getting the var id
                        $varid = get_field('guidedquiz_var', 'id', 'quizid', $quizid, 'varname', $varname);

                        $random = get_field('guidedquiz_val', 'varvalues', 'guidedquizvarid', $varid, 'attemptid', $attemptid);
                        if (!$random) {
                            print_error('errornorandomvaluesdata', 'qtype_programmedresp');
                        }

                        $randomvalues = array_merge($randomvalues, programmedresp_unserialize($random));
                    }
                }

                break;
        }

        // If 1 is the array size the param type is an integer|float
        if (count($randomvalues) == 1) {
            $value = $randomvalues[0];

            // Return it as a string to eval()
        } else {
            $value = $this->get_function_params_array($randomvalues);
        }

        return $value;
    }

    function get_function_params_array($randomvalues) {

        $value = 'array(';
        foreach ($randomvalues as $randomvalue) {
            $arrayvalues[] = $randomvalue;
        }
        $value.= implode(', ', $arrayvalues);
        $value.= ')';

        return $value;
    }

    /**
     * Checks the user response against the function response
     *
     * @param mixed $result An integer or infinite
     * @param mixed $response An integer or infinite
     * @param object $programmedresp The question_programmedresp object to get tolerance
     * @return boolean
     */
    function test_programmed_response($result, $response, $programmedresp) {

        if (strval($result) == strval($response)) {
            return 1;
        }

        // Just for 0 values
        if ($result === 0 && $response == '') {
            return 1;
        }

        // If it's not an integer nor a float it's a string
        if (!programmedresp_is_numeric($response)) {
            // strval() vs strval() has been previously tested
            return 0;
        }

        // Tolerance nominal
        if ($programmedresp->tolerancetype == PROGRAMMEDRESP_TOLERANCE_NOMINAL) {
            if (floatval($response - $programmedresp->tolerance) <= floatval($result) && floatval($response + $programmedresp->tolerance) >= floatval($result)) {
                return 1;
            }

            // Tolerance relative
        } else if (floatval($response * (1 - $programmedresp->tolerance)) < floatval($result) && floatval($response * (1 + $programmedresp->tolerance)) > floatval($result)) {
            return 1;
        }

        return 0;
    }

//    function compare_responses($question, $state, $teststate) {
//        // TODO write the code to return two different student responses, and
//        // return two if the should be considered the same.
//        return false;
//    }
//
//    /**
//     * Checks whether a response matches a given answer, taking the tolerance
//     * and units into account. Returns a true for if a response matches the
//     * answer, false if it doesn't.
//     */
//    function test_response(&$question, &$state, $answer) {
//        // TODO if your code uses the question_answer table, write a method to
//        // determine whether the student's response in $state matches the
//        // answer in $answer.
//        return false;
//    }
//
//    function check_response(&$question, &$state){
//        // TODO
//        return false;
//    }

    /**
     * Calculates the question response through the selected function and the random args from question_programmedresp_val
     *
     * @param $question
     * @param $state
     * @return array Correct responses with format array('ARGNUM' => VALUE, ....)
     */
    function get_correct_responses_without_round(&$question, &$state) {

        global $CFG;

        // Get the function which calculates the response
        if (!$programmedresp = get_record('qtype_programmedresp', 'question', $state->question)) {
            return false;   
        }

        $function = get_record('qtype_programmedresp_f', 'id', $programmedresp->programmedrespfid);
        $args = get_records('qtype_programmedresp_arg', 'programmedrespid', $programmedresp->id, 'argkey ASC');
        $vars = get_records('qtype_programmedresp_var', 'programmedrespid', $programmedresp->id);

        // Executes the function and stores the result/s in $results var
        $exec = '$results = ' . $function->name . '(';


        $modname = programmedresp_get_modname();
        $quizid = programmedresp_get_quizid($state->attempt, $modname);

        foreach ($args as $arg) {
            $execargs[] = $this->get_exec_arg($arg, $vars, $state->attempt, $quizid);
        }
        $exec.= implode(', ', $execargs);
        $exec.= ');';

        // Remove the output generated
        $exec = 'ob_start();' . $exec . 'ob_end_clean();';

        eval($exec);

        if (!is_array($results)) {
            $results = array($results);
        }

        return $results;
    }

    /**
     * Common question engine interface
     *
     * @uses get_correct_responses_without_round and applies the rounding
     * @param unknown_type $question
     * @param unknown_type $state
     */
    function get_correct_responses(&$question, &$state) {

        // Get the function which calculates the response
        if (!$programmedresp = get_record('qtype_programmedresp', 'question', $state->question)) {
            return false;
        }

        $results = $this->get_correct_responses_without_round($question, $state);

        // Show the correct response with the same number of decimals of tolerance
        foreach ($results as $key => $result) {
            $results[$key] = programmedresp_round($result, $programmedresp->tolerance);
        }

        return $results;
    }

    function get_all_responses(&$question, &$state) {
        return $state->responses;
    }

    function get_actual_response($question, $state) {
        return $state->responses;
    }

    function response_summary($question, $state, $length = 60) {
        $responses = $this->get_actual_response($question, $state);
        return implode(', ', $responses);
    }

    /**
     * Backup the data in the question
     *
     * This is used in question/backuplib.php
     */
    function backup($bf, $preferences, $question, $level = 6) {

        $status = parent::backup($bf, $preferences, $question, $level);

        $programmedresp = get_record('qtype_programmedresp', 'question', $question);

        $vars = get_records('qtype_programmedresp_var', 'programmedrespid', $programmedresp->id);
        $args = get_records('qtype_programmedresp_arg', 'programmedrespid', $programmedresp->id);
        $resps = get_records('qtype_programmedresp_resp', 'programmedrespid', $programmedresp->id);

//        if (!$vars || !$args || !$resps) {
//            return false;
//        }
        // Vars
        if ($vars) {
            fwrite($bf, start_tag('VARS', $level, true));
            foreach ($vars as $var) {

                fwrite($bf, start_tag('VAR', $level + 1, true));
                foreach ($this->exportvarfields as $field) {
                    fwrite($bf, full_tag(strtoupper($field), $level + 2, false, $var->$field));
                }
                fwrite($bf, end_tag('VAR', $level + 1, true));
                $varsmap[$var->id] = $var->varname;
            }
            fwrite($bf, end_tag('VARS', $level, true));
        }

        // Args
        $concatvars = array();
        fwrite($bf, start_tag('ARGS', $level, true));
        if ($args) {
            foreach ($args as $arg) {

                // Changing var id reference to varname
                if ($arg->type == PROGRAMMEDRESP_ARG_VARIABLE) {
                    $arg->value = $varsmap[$arg->value];
                }

                fwrite($bf, start_tag('ARG', $level + 1, true));
                foreach ($this->exportargfields as $field) {
                    fwrite($bf, full_tag(strtoupper($field), $level + 2, false, $arg->$field));
                }
                fwrite($bf, end_tag('ARG', $level + 1, true));

                // If it's a concatvar backup it
                if ($arg->type == PROGRAMMEDRESP_ARG_CONCAT) {
                    $concatvars[$arg->value] = $arg->value;
                }
            }
        }
        fwrite($bf, end_tag('ARGS', $level, true));

        // Concat vars
        if (!empty($concatvars)) {
            fwrite($bf, start_tag('CONCATVARS', $level, true));
            foreach ($concatvars as $concatid) {

                if (!$concatvar = get_record('qtype_programmedresp_conc', 'id', $concatid)) {
                    $status = false;
                }

                fwrite($bf, start_tag('CONCATVAR', $level + 1, true));
                // Adding the id to map when restoring
                fwrite($bf, full_tag('ID', $level + 2, false, $concatvar->id));
                foreach ($this->exportconcatvarfields as $field) {
                    fwrite($bf, full_tag(strtoupper($field), $level + 2, false, $concatvar->$field));
                }
                fwrite($bf, end_tag('CONCATVAR', $level + 1, true));
            }
            fwrite($bf, end_tag('CONCATVARS', $level, true));
        }

        // Resps
        fwrite($bf, start_tag('RESPS', $level, true));
        if ($resps) {
            foreach ($resps as $resp) {

                fwrite($bf, start_tag('RESP', $level + 1, true));
                foreach ($this->exportrespfields as $field) {
                    fwrite($bf, full_tag(strtoupper($field), $level + 2, false, $resp->$field));
                }
                fwrite($bf, end_tag('RESP', $level + 1, true));
            }
        }
        fwrite($bf, end_tag('RESPS', $level, true));


        // Function
        fwrite($bf, start_tag('FUNCTION', $level, true));
        $function = get_record('qtype_programmedresp_f', 'id', $programmedresp->programmedrespfid);
        foreach ($this->exportfunctionfields as $field) {
            fwrite($bf, full_tag(strtoupper($field), $level + 1, false, $function->$field));
        }

        // Adding function code
        $functioncode = programmedresp_get_function_code($function->name);
        fwrite($bf, full_tag('CODE', $level + 1, false, $functioncode));
        fwrite($bf, end_tag('FUNCTION', $level, true));

        return $status;
    }

    /**
     * Restores the data in the question
     *
     * This is used in question/restorelib.php
     */
    function restore($old_question_id, $new_question_id, $info, $restore) {

        $status = parent::restore($old_question_id, $new_question_id, $info, $restore);

        $programmedresp = get_record('qtype_programmedresp', 'question', $new_question_id);

        // Vars
        $var->programmedrespid = $programmedresp->id;
        if (!empty($info['#']['VARS'])) {
            foreach ($info['#']['VARS'][0]['#']['VAR'] as $vardata) {

                foreach ($this->exportvarfields as $field) {
                    $var->$field = backup_todb($vardata['#'][strtoupper($field)][0]['#']);
                }
                if (!$varsmap[$var->varname] = insert_record('qtype_programmedresp_var', $var)) {
                    return false;
                }
            }
        }

        // Args
        $arg->programmedrespid = $programmedresp->id;
        if ($info['#']['ARGS'][0]['#']['ARG']) {
            foreach ($info['#']['ARGS'][0]['#']['ARG'] as $argdata) {

                $arg->argkey = backup_todb($argdata['#']['ARGKEY'][0]['#']);
                $arg->type = backup_todb($argdata['#']['TYPE'][0]['#']);
                $arg->value = backup_todb($argdata['#']['VALUE'][0]['#']);

                // Getting the var id
                if ($arg->type == PROGRAMMEDRESP_ARG_VARIABLE) {
                    $argumentvar = get_record('qtype_programmedresp_var', 'programmedrespid', $programmedresp->id, 'varname', $arg->value, '', '', 'id');
                    $arg->value = $argumentvar->id;
                }

                if (!$argnewid = insert_record('qtype_programmedresp_arg', $arg)) {
                    return false;
                }

                // If it's a concat var we must maintain the mapping between arg and concat var
                // to update argument->value
                if ($arg->type == PROGRAMMEDRESP_ARG_CONCAT) {
                    $argconcatmapping[$arg->value] = $argnewid;
                }
            }
        }

        // Concat vars
        if (!empty($info['#']['CONCATVARS'])) {
            foreach ($info['#']['CONCATVARS'][0]['#']['CONCATVAR'] as $concatvardata) {

                foreach ($this->exportconcatvarfields as $field) {
                    $concat->$field = backup_todb($concatvardata['#'][strtoupper($field)][0]['#']);
                }
                $concat->instanceid = $programmedresp->id;
                if (!$newid = insert_record('qtype_programmedresp_conc', $concat)) {
                    $status = false;
                    continue;
                }

                // Updating the argument which uses the concatvar
                $oldid = backup_todb($concatvardata['#']['ID'][0]['#']);
                if (empty($argconcatmapping[$oldid])) {
                    $status = false;
                    continue;
                }
                $concatarg = get_record('qtype_programmedresp_arg', 'id', $argconcatmapping[$oldid]);
                $concatarg->value = $newid;
                update_record('qtype_programmedresp_arg', $concatarg);
            }
        }

        // Resps
        $resp->programmedrespid = $programmedresp->id;
        if ($info['#']['RESPS'][0]['#']['RESP']) {
            foreach ($info['#']['RESPS'][0]['#']['RESP'] as $respdata) {

                foreach ($this->exportrespfields as $field) {
                    $resp->$field = backup_todb($respdata['#'][strtoupper($field)][0]['#']);
                }
                if (!insert_record('qtype_programmedresp_resp', $resp)) {
                    return false;
                }
            }
        }

        // Function
        $functionname = backup_todb($info['#']['FUNCTION'][0]['#']['NAME'][0]['#']);
        $functioncode = $info['#']['FUNCTION'][0]['#']['CODE'][0]['#'];
        if (!$function = get_record('qtype_programmedresp_f', 'name', $functionname)) {

            foreach ($this->exportfunctionfields as $field) {
                $function->$field = backup_todb($info['#']['FUNCTION'][0]['#'][strtoupper($field)][0]['#']);
            }

            // Adding the functions to the default functions category
            $function->programmedrespfcatid = programmedresp_check_base_functions_category();
            if (!$function->id = insert_record('qtype_programmedresp_f', $function)) {
                return false;
            }

            // TODO: Clean code
            programmedresp_add_repository_function($functioncode);


            // If the function already exists ensure that it is the same function
        } else if (rtrim(programmedresp_get_function_code($functionname)) != rtrim($functioncode)) {
            return false;
        }

        // Updating programmedresp function id
        if ($programmedresp->programmedrespfid != $function->id) {
            $programmedresp->programmedrespfid = $function->id;
            update_record('qtype_programmedresp', $programmedresp);
        }

        return $status;
    }

    function is_usable_by_random() {
        return false;
    }

    /**
     * Export for MoodleXML format programmedresp questions
     * @param $question
     * @param $format
     * @param $extra
     */
    function export_to_xml($question, $format, $extra = null) {

        $xmlstring = '';

        // Programmedresp
        foreach ($this->programmedrespfields as $field) {
            $xmlstring .= '    <' . $field . '>' . $question->options->programmedresp->{$field} . '</' . $field . '>' . chr(13) . chr(10);
        }

        // Vars
        $xmlstring .= '    <vars>' . chr(13) . chr(10);
        if (!empty($question->options->vars)) {
            foreach ($question->options->vars as $var) {
                $xmlstring .= '      <var>' . chr(13) . chr(10);
                foreach ($this->exportvarfields as $field) {
                    $xmlstring .= '        <' . $field . '>' . $var->{$field} . '</' . $field . '>' . chr(13) . chr(10);

                    // Storing the varid => varname relation
                    $programmedrespvars[$var->id] = $var->varname;
                }
                $xmlstring .= '      </var>' . chr(13) . chr(10);
            }
        }
        $xmlstring .= '    </vars>' . chr(13) . chr(10);

        // Concat vars
        if (!empty($question->options->concatvars)) {
            $xmlstring .= '    <concatvars>' . chr(13) . chr(10);
            foreach ($question->options->concatvars as $var) {
                $xmlstring .= '      <concatvar>' . chr(13) . chr(10);
                foreach ($this->exportconcatvarfields as $field) {
                    $xmlstring .= '        <' . $field . '>' . $var->{$field} . '</' . $field . '>' . chr(13) . chr(10);

                    // Storing the varid => concatvarname relation
                    $programmedrespconcatvars[$var->id] = $var->name;
                }
                $xmlstring .= '      </concatvar>' . chr(13) . chr(10);
            }
            $xmlstring .= '    </concatvars>' . chr(13) . chr(10);
        }

        // Args
        $xmlstring .= '    <args>' . chr(13) . chr(10);
        if (!empty($question->options->args)) {
            foreach ($question->options->args as $arg) {
                $xmlstring .= '      <arg>' . chr(13) . chr(10);
                $xmlstring .= '        <argkey>' . $arg->argkey . '</argkey>' . chr(13) . chr(10);
                $xmlstring .= '        <type>' . $arg->type . '</type>' . chr(13) . chr(10);

                // The reference to the var id should be changed for a varname reference
                if ($arg->type == PROGRAMMEDRESP_ARG_VARIABLE) {
                    $arg->value = $programmedrespvars[$arg->value];

                    // The reference to the concat var id should be changed for it's var name
                } else if ($arg->type == PROGRAMMEDRESP_ARG_CONCAT) {
                    $arg->value = $programmedrespconcatvars[$arg->value];
                }

                $xmlstring .= '        <value>' . $arg->value . '</value>' . chr(13) . chr(10);
                $xmlstring .= '      </arg>' . chr(13) . chr(10);
            }
        }
        $xmlstring .= '    </args>' . chr(13) . chr(10);

        // Resps
        $xmlstring .= '    <resps>' . chr(13) . chr(10);
        if (!empty($question->options->resps)) {
            foreach ($question->options->resps as $resp) {
                $xmlstring .= '      <resp>' . chr(13) . chr(10);
                $xmlstring .= '        <returnkey>' . $resp->returnkey . '</returnkey>' . chr(13) . chr(10);
                $xmlstring .= '        <label>' . $format->writetext($resp->label, 4) . '</label>' . chr(13) . chr(10);
                $xmlstring .= '      </resp>' . chr(13) . chr(10);
            }
        }
        $xmlstring .= '    </resps>' . chr(13) . chr(10);

        // Function
        $xmlstring .= '    <function>' . chr(13) . chr(10);
        $function = get_record('qtype_programmedresp_f', 'id', $question->options->programmedresp->programmedrespfid);
        foreach ($this->exportfunctionfields as $field) {

            if ($field == 'description' || $field == 'params' || $field == 'results') {
                $function->{$field} = $format->writetext($function->$field, 4);
            }
            $xmlstring .= '      <' . $field . '>' . $function->{$field} . '</' . $field . '>' . chr(13) . chr(10);
        }

        // Adding function code
        $functioncode = programmedresp_get_function_code($function->name);

        $xmlstring .= '      <code>' . $functioncode . '</code>' . chr(13) . chr(10);
        $xmlstring .= '    </function>' . chr(13) . chr(10);

        return $xmlstring;
    }

    /**
     * Import for MoodleXML format programmedresp questions
     * @param $data
     * @param $question
     * @param $format
     * @param $extra
     */
    function import_from_xml($data, $question, $format, $extra = null) {

        $qo = parent::import_from_xml($data, $question, $format, $extra);

        // Vars
        if (!empty($data['#']['vars'][0]['#']['var'])) {
            foreach ($data['#']['vars'][0]['#']['var'] as $key => $vardata) {
                foreach ($this->exportvarfields as $paramkey => $paramname) {
                    $qo->vars[$key]->{$paramname} = $vardata['#'][$paramname][0]['#'];
                }
            }
        }

        // Concat vars
        if (!empty($data['#']['concatvars'][0]['#']['concatvar'])) {
            foreach ($data['#']['concatvars'][0]['#']['concatvar'] as $key => $vardata) {
                foreach ($this->exportconcatvarfields as $paramkey => $paramname) {
                    $qo->concatvars[$key]->{$paramname} = $vardata['#'][$paramname][0]['#'];
                }
            }
        }

        // Args
        if (!empty($data['#']['args'][0]['#']['arg'])) {
            foreach ($data['#']['args'][0]['#']['arg'] as $key => $argdata) {
                foreach ($this->exportargfields as $paramkey => $paramname) {
                    $qo->args[$key]->{$paramname} = $argdata['#'][$paramname][0]['#'];
                }
            }
        }

        // Resps
        if (!empty($data['#']['resps'][0]['#']['resp'])) {
            foreach ($data['#']['resps'][0]['#']['resp'] as $key => $respdata) {
                $qo->resps[$key]->returnkey = $respdata['#']['returnkey'][0]['#'];
                $qo->resps[$key]->label = $format->import_text($respdata['#']['label'][0]['#']['text']);
            }
        }

        // Function
        $functionname = $data['#']['function'][0]['#']['name'][0]['#'];

        $functioncode = $data['#']['function'][0]['#']['code'][0]['#'];
        if (!$function = get_record('qtype_programmedresp_f', 'name', $functionname)) {

            foreach ($this->exportfunctionfields as $field) {

                if ($field == 'description' || $field == 'params' || $field == 'results') {
                    $function->$field = $format->import_text($data['#']['function'][0]['#'][$field][0]['#']['text']);
                } else {
                    $function->$field = $data['#']['function'][0]['#'][$field][0]['#'];
                }
            }

            // Adding the functions to the default functions category
            $function->programmedrespfcatid = programmedresp_check_base_functions_category();
            if (!$function->id = insert_record('qtype_programmedresp_f', $function)) {
                return false;
            }

            // TODO: Clean code
            programmedresp_add_repository_function($functioncode);


            // If the function already exists ensure that it is the same function
            // TODO: Improve checking (number of chars for example)
        } else if (rtrim(programmedresp_get_function_code($functionname)) != rtrim($functioncode)) {
            return false;
        }

        // Updating programmedresp function id
        if ($qo->programmedrespfid != $function->id) {
            $qo->programmedrespfid = $function->id;
        }

        return $qo;
    }

}

// Register this question type with the system.
question_register_questiontype(new programmedresp_qtype());
?>
