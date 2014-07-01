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
 * programmedresp question definition class.
 *
 * @package    qtype
 * @subpackage programmedresp
 * @copyright  THEYEAR YOURNAME (YOURCONTACTINFO)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/programmedresp/lib.php');
programmedresp_check_datarootfile();
require_once($CFG->dataroot . '/qtype_programmedresp.php');

/**
 * Represents a programmedresp question.
 *
 * @copyright  THEYEAR YOURNAME (YOURCONTACTINFO)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_programmedresp_question extends question_graded_automatically {

    public $options;
    public $attemptid;
    public $q_usage_id;
    public $resps;
    public $answers;

    /**
     * Start a new attempt at this question, storing any information that will
     * be needed later in the step.
     *
     * This is where the question can do any initialisation required on a
     * per-attempt basis. For example, this is where the multiple choice
     * question type randomly shuffles the choices (if that option is set).
     *
     * Any information about how the question has been set up for this attempt
     * should be stored in the $step, by calling $step->set_qt_var(...).
     *
     * @param question_attempt_step The first step of the {@link question_attempt}
     *      being started. Can be used to store state.
     * @param int $varant which variant of this question to start. Will be between
     *      1 and {@link get_num_variants()} inclusive.
     */
    public function start_attempt(question_attempt_step $step, $variant) {
        //debugging("start attempt");
    }

    /**
     * When an in-progress {@link question_attempt} is re-loaded from the
     * database, this method is called so that the question can re-initialise
     * its internal state as needed by this attempt.
     *
     * For example, the multiple choice question type needs to set the order
     * of the choices to the order that was set up when start_attempt was called
     * originally. All the information required to do this should be in the
     * $step object, which is the first step of the question_attempt being loaded.
     *
     * @param question_attempt_step The first step of the {@link question_attempt}
     *      being loaded.
     */
    public function apply_attempt_state(question_attempt_step $step) {
        global $DB;
        $attemptid = $this->get_attemptid_by_stepid($step->get_id());
        $this->attemptid = $this->get_question_usageid($attemptid);
        //$attemptid = $this->q_usage_id;
        debugging("in apply attempt state. Attempt id = " . $this->attemptid);
        $modname = programmedresp_get_modname();
        // Replacing vars for random values
        if (!empty($this->options->vars)) {
            foreach ($this->options->vars as $var) {        //{$x}
                // If this attempt doesn't have yet a value
                if (!$values = $DB->get_field('qtype_programmedresp_val', 'varvalues', array('attemptid' => $this->attemptid, 'programmedrespvarid' => $var->id, 'module' => $modname))) {
                    //Add a new random value
                    $values = $this->generate_value($this->attemptid, $var, $modname);
                    if (is_null($values)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }
                }
                $values = programmedresp_unserialize($values);

                $valuetodisplay = implode(', ', $values);
                //echo '<br>values to display=' . $valuetodisplay;
                $this->questiontext = str_replace('{$' . $var->varname . '}', $valuetodisplay, $this->questiontext);
            }
        }

        $answers = $this->get_correct_responses_without_round($this->attemptid);
        foreach ($answers as $key => $ansvalue) {
            $this->answers[$key]->answer = $ansvalue;
            $this->answers[$key]->answerformat = 1;
        }
        //print_r($this->answers);
    }

    public function get_correct_response() {

        if (empty($this->answers[0]->answer)) {
            debugging("getcorrect response() withous response yet");
            return null;
        }
        $response = array();
        foreach ($this->resps as $resp) {
            $response[$this->field($resp->returnkey)] = $this->answers[
                    $resp->returnkey]->answer;
        }
        debugging("getcorrect response() with response");
        return $response;
    }

    /**
     * @param int $key choice number
     * @return string the question-type variable name.
     */
    public function field($key) {
        return 'progrespkey' . $key;
    }

    /**
     * What data may be included in the form submission when a student submits
     * this question in its current state?
     *
     * This information is used in calls to optional_param. The parameter name
     * has {@link question_attempt::get_field_prefix()} automatically prepended.
     *
     * @return array|string variable name => PARAM_... constant, or, as a special case
     *      that should only be used in unavoidable, the constant question_attempt::USE_RAW_DATA
     *      meaning take all the raw submitted data belonging to this question.
     */
    public function get_expected_data() {
        debugging("get_expected_data");
        $expected = array();
        foreach ($this->resps as $resp) {
            $expected[$this->field($resp->returnkey)] = PARAM_RAW_TRIMMED;
        }
        debugging(print_r($expected));
        return $expected;
    }

    public function get_validation_error(array $response) {
        debugging("get_validation_error");
        if ($this->is_gradable_response($response)) {
            return '';
        }
        return get_string('pleaseselectatleastoneanswer', 'qtype_multichoice');
    }

    public function grade_response(array $response) {
        debugging("grade_response");
        // Stores the average grade
        $fractions = array();
        $nresponses = 0;
        foreach ($this->answers as $resultkey => $answer) {

            if (empty($response[$this->field($resultkey)])) {
                $response[$this->field($resultkey)] = '';
            }
            $fractions[] = $this->test_programmed_response($answer->answer, $response[$this->field($resultkey)], $this->options->programmedresp);
            $nresponses++;
        }

        $raw_grade = (float) array_sum($fractions) / $nresponses;
        $raw_grade = min(max((float) $raw_grade, 0.0), 1.0) * 1;
        debugging("the fraction for this responses is : " . $raw_grade);

        return array($raw_grade, question_state::graded_state_for_fraction($raw_grade));
    }

    /**
     * Use by many of the behaviours to determine whether the student
     * has provided enough of an answer for the question to be graded automatically,
     * or whether it must be considered aborted.
     *
     * @param array $response responses, as returned by
     *      {@link question_attempt_step::get_qt_data()}.
     * @return bool whether this response can be graded.
     */
    public function is_complete_response(array $response) {
        debugging("is_complete_response");
        foreach ($this->resps as $resp) {
            $answerid = $this->field($resp->returnkey);
            if (array_key_exists($answerid, $response) &&
                    ($response[$answerid] || $response[$answerid] === '0' || $response[$answerid] === 0)) {
                debugging("is_complete_response");
                return true;
            }
        }
        debugging("Isn't complete response");
        return false;
    }

    /*
     * resposta es sempre 'answer + resp->returnkey' per identificar de forma única cada resposta dins UNA PREGUNTA
     */

    public function is_gradable_response(array $response) {
        debugging("is_gradable_response");
        return $this->is_complete_response($response);
    }

    public function is_same_response(array $prevresponse, array $newresponse) {

        foreach ($this->resps as $resp) {
            $fieldname = $this->field($resp->returnkey);
            if (!question_utils::arrays_same_at_key($prevresponse, $newresponse, $fieldname)) {
                debugging("is same response false");
                return false;
            }
        }
        debugging("is same response true");
        return true;
    }

    public function summarise_response(array $response) {
        return '';
    }

    public function classify_response(array $response) {
        return array();
    }

    //helper methods

    /**
     * Generates a value based on $var attributes and inserts in into DB
     *
     * @param $attemptid
     * @param $var
     * @param $modname
     * @return null|string
     */
    //no necessito insertarlos a la base de dades específica
    function generate_value($attemptid, $var, $modname = false) {
        global $DB;
        if (!$modname) {
            $modname = programmedresp_get_modname();
        }

        $programmedrespval->module = $modname;
        $programmedrespval->attemptid = $attemptid;
        $programmedrespval->programmedrespvarid = $var->id;
        $programmedrespval->varvalues = programmedresp_serialize(programmedresp_get_random_value($var));
        if (!$DB->insert_record('qtype_programmedresp_val', $programmedrespval)) {
            return null;
        }
        $values = $programmedrespval->varvalues;

        return $values;


        //return programmedresp_serialize(programmedresp_get_random_value($var));
    }

    /**
     * Calculates the question response through the selected function and the random args from question_programmedresp_val
     *
     * @param $question
     * @param $state
     * @return array Correct responses with format array('ARGNUM' => VALUE, ....)
     */
    function get_correct_responses_without_round($attemptid) {
        //echo '<br> get_correct_responses_withoyt_round';

        /* Get the function which calculates the response
          //JA ho obting des de get_question_options
          if (!$programmedresp = $DB->get_record('qtype_programmedresp', array('question' => $this->id))) {

          return false;
          }

          $function = $DB->get_record('qtype_programmedresp_f', array('id' => $programmedresp->programmedrespfid));
          $args = $DB->get_records('qtype_programmedresp_arg', array('programmedrespid' => $programmedresp->id), 'argkey ASC');
          $vars = $DB->get_records('qtype_programmedresp_var', array('programmedrespid'=> $programmedresp->id));

         */

        // Executes the function and stores the result/s in $results var
        //echo "<br>function name = ".$this->options->function->name;
        $exec = '$results = ' . $this->options->function->name . '(';


        $modname = programmedresp_get_modname();
        echo '<br>attemptid = ' . $attemptid;
        //echo "<br> modname = ".$modname;
        $quizid = programmedresp_get_quizid($attemptid, $modname);

        //echo "<br> quizid = " . $quizid;

        foreach ($this->options->args as $arg) {

            $execargs[] = $this->get_exec_arg($arg, $this->options->vars, $attemptid, $quizid);
        }
        $exec.= implode(', ', $execargs);
        $exec.= ');';

        // Remove the output generated

        $exec = 'ob_start();' . $exec . 'ob_end_clean();';
        //debugging($exec);
        eval($exec);

        if (!is_array($results)) {
            $results = array($results);
        }

        return $results;
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

        global $CFG, $DB;

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
                echo "<br>get_exec_arg()->variable programmedresp";
                $randomvalues = $DB->get_field('qtype_programmedresp_val', 'varvalues', array('attemptid' => $attemptid, 'programmedrespvarid' => $arg->value, 'module' => $modname));

                // If the random value was not previously created let's create it (for example, answer a quiz where this question has not been shown)
                if (!$randomvalues) {

                    // Var data
                    $vardata = $DB->get_record('qtype_programmedresp_var', array('id' => $arg->value));
                    $values = $this->generate_value($attemptid, $vardata);
                    if (is_null($values)) {
                        print_error('errornorandomvaluesdata', 'qtype_programmedresp');
                    }
                    $randomvalues = $values;
                }
                $randomvalues = programmedresp_unserialize($randomvalues);
                debugging("random values  = " . print_r($randomvalues));
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

                    $random = $DB->get_field('qtype_programmedresp_val', 'varvalues', array('attemptid' => $attemptid, 'programmedrespvarid' => $varid, 'module' => $modname));
                    if (!$random) {
                        print_error('errornorandomvaluesdata', 'qtype_programmedresp');
                    }
                    $randomvalues = array_merge($randomvalues, programmedresp_unserialize($random));
                }

                break;

            case PROGRAMMEDRESP_ARG_EXTENDEDQUIZ:

                echo "<br>get_exec_arg()->variable extendedquiz";
                // Getting the argument variable
                $sql = "SELECT * FROM {$CFG->prefix}extendedquiz_var_arg gva
            	        WHERE gva.quizid = '$quizid' AND gva.programmedrespargid = '{$arg->id}'";


                if (!$vardata = $DB->get_record_sql($sql)) {
                    print_error('errorargumentnoassigned', 'qtype_programmedresp');
                }


                /* if (!$vardata = $DB->get_record('extendedquiz_var_arg', array('quizid' => $quizid, 'programmedrespargid' => $arg->id))) {
                  print_error('errorargumentnoassigned', 'qtype_programmedresp');
                  } */

                // To store the values
                $randomvalues = array();

                // A var
                if ($vardata->type == 'var') {

                    $random = $DB->get_field('extendedquiz_val', 'varvalues', array('extendedquizvarid' => $vardata->instanceid, 'attemptid' => $attemptid));
                    $randomvalues = programmedresp_unserialize($random);

                    // A concat var
                } else {

                    $var = $DB->get_record('qtype_programmedresp_conc', array('id' => $vardata->instanceid));
                    if (!$var) {
                        print_error('errorargumentnoassigned', 'qtype_programmedresp');
                    }

                    // Adding each concatenated variable to $randomvalues
                    $varnames = programmedresp_unserialize($var->vars);
                    foreach ($varnames as $varname) {

                        // Getting the var id
                        $varid = $DB->get_field('extendedquiz_var', 'id', array('quizid' => $quizid, 'varname' => $varname));

                        $random = $DB->get_field('extendedquiz_val', 'varvalues', array('extendedquizvarid' => $varid, 'attemptid' => $attemptid));
                        if (!$random) {
                            //print_error('errornorandomvaluesdata', 'qtype_programmedresp');
                            break;
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
            echo "<br>PROGRAMMEDRESP_TOLERANCE_NOMINAL= " . $programmedresp->tolerance;
            //echo "  float value of result= ".floatval($result);

            if (floatval($response - $programmedresp->tolerance) <= floatval($result) && floatval($response + $programmedresp->tolerance) >= floatval($result)) {
                return 1;
            }

            // Tolerance relative
        } else if (floatval($response * (1 - $programmedresp->tolerance)) < floatval($result) && floatval($response * (1 + $programmedresp->tolerance)) > floatval($result)) {

            return 1;
        }

        return 0;
    }

    public function get_attemptid_by_stepid($stepid) {
        global $DB;

        if (!$attempid = $DB->get_field('question_attempt_steps', 'questionattemptid', array('id' => $stepid))) {
            echo "<br>upss.... get_attemptid_by_stepid()";
        }
        return $attempid;
    }

    public function get_question_usageid($attemptid) {
        global $DB;

        if (!$questionusage = $DB->get_field('question_attempts', 'questionusageid', array('id' => $attemptid))) {
            echo "<br>upss.... get_question_usageid()";
        }
        return $questionusage;
    }

    public function is_correct_answer($ansid, question_attempt $qa) {
        $fraction = $this->test_programmed_response($this->answers[$ansid]->answer, $qa->get_last_qt_var($this->field($ansid)), $this->options->programmedresp);

        return $fraction;
    }

    public function make_html_inline($html) {
        $html = preg_replace('~\s*<p>\s*~u', '', $html);
        $html = preg_replace('~\s*</p>\s*~u', '<br />', $html);
        $html = preg_replace('~(<br\s*/?>)+$~u', '', $html);
        return trim($html);
    }

}
