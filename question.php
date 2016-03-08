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
 * @copyright  2016 Gerard Cuello (gerard.urv@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/programmedresp/lib.php');
// TODO Review it: I think the question engine API has some method to do that!
//File to store the php functions used to calculate the response
programmedresp_check_datarootfile();
require_once($CFG->dataroot . '/qtype_programmedresp.php');
// END TODO

/**
 * Represents a programmedresp question.
 *
 * @copyright 2016 Gerard Cuello (gerard.urv@gmail.com)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_programmedresp_question extends question_graded_automatically {

    /**
     *
     * @var int the unique id that identify an attempt.
     */
    public $usageid = null;

    /**
     *
     * @var array stores the correct answers for this question.
     */
    public $answers = null;

    /**
     *
     * @var array with the random values of the variables of this question.
     */
    public $varvalues = array();

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
    public function start_attempt(\question_attempt_step $step, $variant) {
        // vars loaded by questiontype->initialize_question_instance()
        foreach ($this->vars as $var) {
            $values = programmedresp_get_random_value($var);
            if (!$values) {
                print_error('errordb', 'qtype_programmedresp');
            }
            $valuetodisplay = implode(', ', $values);
            str_replace('{$' . $var->varname . '}', $valuetodisplay, $this->questiontext, $count);
            // If $var->varname is found in questiontext ($count == true) store it
            $count && $step->set_qt_var('_var_' . $var->id, $valuetodisplay);
        }
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
    public function apply_attempt_state(\question_attempt_step $step) {
        global $DB;

        $attemptid = $this->get_attemptid_by_stepid($step->get_id());
        $this->usageid = $this->get_question_usageid($attemptid);

        // Retrive all vars initialized in start_attempt().
        foreach ($step->get_qt_data() as $name => $value) {
            if (substr($name, 0, 5) === '_var_') {
                $varid = substr($name, 5);
                $varname = $this->vars[$varid]->varname;
                $this->questiontext = str_replace('{$' . $varname . '}', $value, $this->questiontext);
                // Store vars (as array form) to be used later to get the correct response
                $this->varvalues[$varid] = explode(',', $value);
            }
        }
        // TODO: Initialize answer as a question_answer object in questiontype.php
        $answersraw = $this->calculate_correct_response_without_round();
        foreach ($answersraw as $index => $answer) {
            $this->answers[$index] = new stdClass();
            $this->answers[$index]->answer = $answer;
            $this->answers[$index]->fraction = 1;  // ???
        }
    }

    /**
     * Return the String identification for the response label
     *
     * @param int $key choice number
     * @return string the question-type variable name.
     */
    public function field($key) {
        return 'answer' . $key;
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
        $expected = array();
        foreach ($this->expectedresps as $resp) {
            $expected[$this->field($resp->returnkey)] = PARAM_RAW_TRIMMED;
        }
        return $expected;
    }

    /**
     * In situations where is_gradable_response() returns false, this method
     * should generate a description of what the problem is.
     * @return string the message.
     */
    public function get_validation_error(array $response) {
        if ($this->is_gradable_response($response)) {
            return '';
        }
        return get_string('pleaseselectatleastoneanswer', 'qtype_multichoice');
    }

    /**
     * What data would need to be submitted to get this question correct.
     * If there is more than one correct answer, this method should just
     * return one possibility. If it is not possible to compute a correct
     * response, this method should return null.
     *
     * @return array|null parameter name => value.
     */
    public function get_correct_response() {
        //Check whether the answer is calculated
        if (!isset($this->answers)) {
            return null;
        }
        $response = array();
        foreach ($this->expectedresps as $expectedresp) {
            $response[$this->field($resp->returnkey)] = $this->answers[$resp->returnkey]->answer;
        }

        return $response;
    }

    public function summarise_response(array $response) {
        // TODO: return '' once I've known what this method does.
        return 'summarise response';
    }

    public function classify_response(array $response) {
        return array();
    }

    public function is_gradable_response(array $response) {
        foreach ($this->expectedresps as $expectedresp) {
            if (empty($response[$this->field($expectedresp->returnkey)])) {
                return false;
            }
        }
        // Return true whether all responses exist and there are not false
        return true;
    }

    /**
     * Used by many of the behaviours, to work out whether the student's
     * response to the question is complete. That is, whether the question attempt
     * should move to the COMPLETE or INCOMPLETE state.
     *
     * @param array $response responses, as returned by
     *      {@link question_attempt_step::get_qt_data()}.
     * @return bool whether this response is a complete answer to this question.
     */
    public function is_complete_response(array $response) {
        if (!$this->is_gradable_response($response)) {
            return false;
        }
        // TODO: Add some extra testing cases like thousands separator
        // See {@link qtype_numerical_question::is_complete_response}
        return true;
    }

    /**
     * Grade a response to the question, returning a fraction between
     * get_min_fraction() and get_max_fraction(), and the corresponding {@link question_state}
     * right, partial or wrong.
     * @param array $response responses, as returned by
     *      {@link question_attempt_step::get_qt_data()}.
     * @return array (float, integer) the fraction, and the state.
     */
    public function grade_response(array $response) {
        // Stores the average grade
        $fractions = array();
        $nresponses = 0;
        foreach ($this->answers as $index => $answerobj) {

            if (empty($response[$this->field($index)])) {
                $response[$this->field($index)] = '';
            }
            $this->answers[$index]->fraction = $this->test_programmed_response($answerobj->answer, $response[$this->field($index)]);

            $fractions[] = $this->answers[$index]->fraction;
            $nresponses++;
        }

        $raw_grade = (float) array_sum($fractions) / $nresponses;
        $raw_grade = min(max((float) $raw_grade, 0.0), 1.0) * 1;
        return array($raw_grade, question_state::graded_state_for_fraction($raw_grade));
    }

    /**
     * Called by {@link qtype_programmedresp_renderer::formulation_and_controls}
     * to determinate whether the user response is correct
     * @param type $useranswer
     * @param type $answernum
     * @return type
     */
    public function get_matching_answer($useranswer, $answernum) {
        return $this->test_programmed_response($useranswer, $this->answers[$answernum]->answer);
    }

    /**
     * Checks the user response against the function response
     *
     * @param mixed $result An integer or infinite
     * @param mixed $response An integer or infinite
     * @return boolean either correct (1) or incorrect (0)
     */
    function test_programmed_response($result, $response) {

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
        if ($this->tolerancetype == PROGRAMMEDRESP_TOLERANCE_NOMINAL) {

            if (floatval($response - $this->tolerance) <= floatval($result) && floatval($response + $this->tolerance) >= floatval($result)) {
                return 1;
            }

            // Tolerance relative
        } else if (floatval($response * (1 - $this->tolerance)) < floatval($result) && floatval($response * (1 + $this->tolerance)) > floatval($result)) {

            return 1;
        }

        return 0;
    }

    /**
     * Use by many of the behaviours to determine whether the student's
     * response has changed. This is normally used to determine that a new set
     * of responses can safely be discarded.
     *
     * @param array $prevresponse the responses previously recorded for this question,
     *      as returned by {@link question_attempt_step::get_qt_data()}
     * @param array $newresponse the new responses, in the same format.
     * @return bool whether the two sets of responses are the same - that is
     *      whether the new set of responses can safely be discarded.
     */
    public function is_same_response(array $prevresponse, array $newresponse) {
        foreach ($this->expectedresps as $expectedresp) {
            $fieldname = $this->field($expectedresp->returnkey);
            if (!question_utils::arrays_same_at_key($prevresponse, $newresponse, $fieldname)) {
                return false;
            }
        }
        return true;
    }

    public function check_file_access($qa, $options, $component, $filearea, $args, $forcedownload) {
        // TODO.
        if ($component == 'question' && $filearea == 'hint') {
            return $this->check_hint_file_access($qa, $options, $args);
        } else {
            return parent::check_file_access($qa, $options, $component, $filearea, $args, $forcedownload);
        }
    }

    /**
     * Work out a final grade for this attempt, taking into account all the
     * tries the student made.
     * @param array $responses the response for each try. Each element of this
     * array is a response array, as would be passed to {@link grade_response()}.
     * There may be between 1 and $totaltries responses.
     * @param int $totaltries The maximum number of tries allowed.
     * @return numeric the fraction that should be awarded for this
     * sequence of response.
     */
    public function compute_final_grade($responses, $totaltries) {
        return 0;
    }

    /**
     * TODO: Add a nice description to this complex method.
     * @return type
     */
    public function calculate_correct_response_without_round() {

        $quizid = programmedresp_get_quizid($this->usageid);

        $exec = '$results = ' . $this->function->name . '(';
        foreach ($this->args as $arg) {
            $execargs[] = $this->get_exec_arg($arg, $quizid);
        }
        $exec.= implode(', ', $execargs);
        $exec.= ');';

        $exec = 'ob_start();' . $exec . 'ob_end_clean();';
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
    function get_exec_arg($arg, $quizid) {

        global $CFG, $DB;

        switch ($arg->type) {

            case PROGRAMMEDRESP_ARG_FIXED:

                if (strstr($arg->value, ',')) {
                    $randomvalues = explode(',', $arg->value);
                } else {
                    $randomvalues = array($arg->value);
                }

                break;

            case PROGRAMMEDRESP_ARG_VARIABLE:

                $randomvalues = $this->varvalues[$arg->value];
                break;

            case PROGRAMMEDRESP_ARG_CONCAT:

                $concatdata = $this->concatvars[$arg->value];

                $concatvalues = programmedresp_unserialize($concatdata->vars);
                // To store the concatenated vars
                $randomvalues = array();

                // $concatvalues conte els noms de les variables que formen la concatenada.
                // Les variables que la formen pertanyen a la mateixa pregunta que estem responent.
                // Necessitem els valors aleatoris de cada variable que forma la concatendada.
                // Getting the random values of each concat var generated on start_attempt().
                foreach ($concatvalues as $varname) {
                    // Getting the var id
                    foreach ($this->vars as $id => $vardata) {
                        if ($vardata->varname == $varname) {
                            // El nom de la variable concatendada coincideix amb el nom de la
                            // variable definida al enunciat.
                            $varid = $id;
                        }
                    }
                    if (empty($varid)) {
                        print_error('errorcantfindvar', 'qtype_programmedresp', $varname);
                    }

                    // get the concret random values
                    $newrandoms = $this->varvalues[$varid];
                    $randomvalues = array_merge($randomvalues, $newrandoms);
                }

                break;

            case PROGRAMMEDRESP_ARG_LINKER:
                // Getting the argument variable
                $sql = "SELECT *
                          FROM {qtype_linkerdesc_var_arg} lva
                         WHERE lva.quizid = ?
                           AND lva.programmedrespargid = ? ";

                if (!$vardata = $DB->get_record_sql($sql, array($quizid, $arg->id))) {
                    print_error('errorargumentnoassigned', 'qtype_programmedresp');
                }

                // To store the values
                $randomvalues = array();

                // A var
                if ($vardata->type == 'var') {

                    $random = $DB->get_field('qtype_programmedresp_val', 'varvalues', array('varid' => $vardata->instanceid, 'attemptid' => $this->usageid));
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

                        // Getting the var id ?¿? I'm not sure about this: 'question' => $var->question
                        $varid = $DB->get_field('qtype_programmedresp_var', 'id', array('question' => $var->question, 'varname' => $varname));

                        $random = $DB->get_field('qtype_programmedresp_val', 'varvalues', array('varid' => $varid, 'attemptid' => $this->usageid));
                        if (!$random) {
                            print_error('errornorandomvaluesdata', 'qtype_programmedresp');
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

    // TODO: move to new helper class.
    /**
     * Get attemptid by step id
     * @param int $stepid unique identification for this step
     * @return int $attemptid
     */
    public function get_attemptid_by_stepid($stepid) {
        global $DB;

        if (!$attempid = $DB->get_field('question_attempt_steps', 'questionattemptid', array('id' => $stepid))) {
            //TODO : show custom message error
        }
        return $attempid;
    }

    public function get_question_usageid($attemptid) {
        global $DB;

        if (!$questionusage = $DB->get_field('question_attempts', 'questionusageid', array('id' => $attemptid))) {
            //TODO : show custom message error
        }
        return $questionusage;
    }
    // END TODO

}
