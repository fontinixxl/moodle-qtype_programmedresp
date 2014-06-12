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
    
    public $options = array();
    public $correctanswer;
    public $attemptid;
    public $randomval;
    public $questionusage;
    
    
    public function start_attempt(question_attempt_step $step, $variant) {
        echo "<br>start_attempt()";
        
    }     
    
    public function apply_attempt_state(question_attempt_step $step) {
        
        global $DB;
        
        $attemptid = $this->get_attemptid_by_stepid($step->get_id());
        $this->questionusage = $this->get_question_usageid($attemptid);
        echo"<br> in apply attempt";
        //echo "<br> step attempt id = ".$step->get_id();
        echo "<br> correct attempt id = ".$attemptid;
        //$this->attemptid = $step->get_id();
        $modname = programmedresp_get_modname();
        // Replacing vars for random values
        if (!empty($this->options->vars)) {
            foreach ($this->options->vars as $var) {        //{$x}
                
                // If this attempt doesn't have yet a value
                if (!$values = $DB->get_field('qtype_programmedresp_val', 'varvalues', array('attemptid' => $attemptid, 'programmedrespvarid' => $var->id, 'module' => $modname))) {
                    echo "generating new random value";
                    //Add a new random value
                    $values = $this->generate_value($attemptid, $var, $modname);
                    if (is_null($values)) {
                        print_error('errordb', 'qtype_programmedresp');
                        
                    }
                }
                $values = programmedresp_unserialize($values);
                
                $valuetodisplay = implode(', ', $values);
                echo '<br>values to display='.$valuetodisplay;
                
                $this->questiontext = str_replace('{$' . $var->varname . '}', $valuetodisplay, $this->questiontext);   
                
            }
        }else{
            echo "<br> no hi ha vars propies de la Question";
        }
        $this->attemptid = $attemptid;
    }
    
    
    //prova: si funciona s'ha de col·locar més abaix
    public function get_attemptid_by_stepid($stepid){
        global $DB;
        
        if(!$attempid = $DB->get_field('question_attempt_steps', 'questionattemptid', array('id' => $stepid))){
            echo "<br>upss.... get_attemptid_by_stepid()";
        }
        return $attempid;
    }
    
    public function get_question_usageid($attemptid){
        global $DB;
        
        if(!$questionusage = $DB->get_field('question_attempts', 'questionusageid', array('id' => $attemptid))){
            echo "<br>upss.... get_question_usageid()";
        }
        return $questionusage;
        
    }

    
    //fi prova
    public function get_correct_response() {
        
        echo "<br> in get_correct_response()";
        
        /*$correctresponse = $this->get_correct_responses_without_round();
        $response = array();
        foreach ($correctresponse as $value) {
            $response['answer'] = $value;
        }
        return $response;

        */
    }

    public function get_expected_data() {
        echo "<br>get_expected_data()";
        //?¿?¿ el tipus de dades que introduiré a la resposta i les unitats 
        //Copiat de numerical
        $expected = array('answer' => PARAM_RAW_TRIMMED);
        $expected['unit'] = PARAM_RAW_TRIMMED;
        /*
        if ($this->has_separate_unit_field()) {
            $expected['unit'] = PARAM_RAW;
        }
         
        */ 
        return $expected;
    }

    public function get_validation_error(array $response) {
        //todo
    }

    public function grade_response(array $response) {
        
        global $CFG, $DB;
        echo "<br>Grade Responses:<br>";
        
        echo "The student response is = ".$response['answer'];

        //echo "<br>Correct response= ".$this->correctresults;
        // Get the function response data
        if (!$programmedresp = $DB->get_record('qtype_programmedresp', array('question' => $this->id))) {
            return false;
        }
        
        // Executes the selected function and returns the correct response/s
        $correctresults = $this->get_correct_responses_without_round($this->attemptid);
        
        foreach($correctresults as $result => $value){
            echo "<br>correct response = ".$value;
            $resulttograde = $value;
            $this->correctanswer = $resulttograde;
            
        }
        
        
        $fraction = $this->test_programmed_response($resulttograde, $response['answer'], $programmedresp);
        echo "<br>fraction= ".$fraction;
        //$this->correctresults = $this->get_correct_responses_without_round();
        
        // Stores the average grade
        //$fractions = array();
        //$nresponses = 0;
        
        /*foreach ($this->correctresults as $resultkey => $correctresult) {
            echo "calcula la fraccio que li correspon a la resposta";
            if (empty($response[$resultkey])) {
                $state->responses[$resultkey] = '';
            }
            // Compares the response against the correct result
            $fractions[] = $this->test_programmed_response($correctresult, $response[$resultkey], $programmedresp);
            $nresponses++;
        }
        
        
        $fraction = (float) array_sum($fractions) / $nresponses;
        echo '<br>' .$fraction;
        
        return array($fraction, question_state::graded_state_for_fraction($fraction));
         * 
         */
        //$fractions[] = $this->test_programmed_response($this->correctresults, $response[$resultkey], $programmedresp);
        
        //$fraction = $this->test_programmed_response($this->correctresults, $response['answer'], $programmedresp);
        return array($fraction, question_state::graded_state_for_fraction($fraction));
    }

    public function is_gradable_response(array $response) {
        echo "<br>is_gradable_response()";
        return array_key_exists('answer', $response) &&
                ($response['answer'] || $response['answer'] === '0' || $response['answer'] === 0);
    }
    
    public function is_complete_response(array $response) {
        echo "<br>is_complete_response()";
        if (!$this->is_gradable_response($response)) {
            return false;
        }
        echo "<br> is complete response = true";
        return true;
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        if (!question_utils::arrays_same_at_key_missing_is_blank(
                $prevresponse, $newresponse, 'answer')) {
            return false;
        }
        
        return true;
    }

    public function summarise_response(array $response) {
        echo "<br>summarise_response()";
        return "todo";
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
        echo "<br>function name = ".$this->options->function->name;
        $exec = '$results = ' . $this->options->function->name . '(';


        //$modname = programmedresp_get_modname();
        echo '<br>attemptid = '.$this->questionusage;
        //echo "<br> modname = ".$modname;
        $quizid = programmedresp_get_quizid($this->questionusage, 'extendedquiz');
        
        echo "<br> quizid = ".$quizid;
        
        foreach ($this->options->args as $arg) {
            
            $execargs[] = $this->get_exec_arg($arg, $this->options->vars, $attemptid, $quizid);
        }
        $exec.= implode(', ', $execargs);
        $exec.= ');';

        // Remove the output generated
        $exec = 'ob_start();' . $exec . 'ob_end_clean();';
        //echo $exec;
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

        global $CFG,$DB;

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

            case PROGRAMMEDRESP_ARG_EXTENDEDQUIZ:
                echo "<br>get_exec_arg()->variable extendedquiz";
                // Getting the argument variable
                /*$sql = "SELECT * FROM extendedquiz_var_arg gva
            	        WHERE gva.quizid = '$quizid' AND gva.programmedrespargid = '{$arg->id}'";
                        
                
                if (!$vardata = $DB->get_record_sql($sql)) {
                    print_error('errorargumentnoassigned', 'qtype_programmedresp');
                }
                 * 
                 */
                
                if (!$vardata = $DB->get_record('extendedquiz_var_arg', array('quizid' => $quizid, 'programmedrespargid' => $arg->id))) {
                    print_error('errorargumentnoassigned', 'qtype_programmedresp');
                }
                // To store the values
                $randomvalues = array();

                // A var
                if ($vardata->type == 'var') {
                    
                    //dudoso..
                    /*$random=$DB->get_records_sql(" 
                        SELECT
                            ev.varvalues
                        FROM {extendedquiz_val} ev
                        JOIN {extendedquiz_attempts} ea ON ev.attemptid = ea.id
                        WHERE ea.uniqueid = :?", $attemptid);
                        //array('attemptid' => $attemptid));
                        
                    //$random = $DB->get_field('extendedquiz_val', 'varvalues', array('extendedquizvarid' => $vardata->instanceid, 'attemptid' => $attemptid));
                    $randomvalues = programmedresp_unserialize($random);
                    */
                    // A concat var
                } else {

                    $var = $DB->get_record('qtype_programmedresp_conc', array('id'=> $vardata->instanceid));
                    if (!$var) {
                        print_error('errorargumentnoassigned', 'qtype_programmedresp');
                    }

                    // Adding each concatenated variable to $randomvalues
                    $varnames = programmedresp_unserialize($var->vars);
                    foreach ($varnames as $varname) {

                        // Getting the var id
                        $varid = $DB->get_field('extendedquiz_val', 'id', array('quizid' => $quizid, 'varname' => $varname));

                        $random = $DB->get_field('extendedquiz_val', 'varvalues', array('extendedquizvarid' => $varid, 'attemptid' => $attemptid));
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
            echo "<br>";
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
            echo "<br>PROGRAMMEDRESP_TOLERANCE_NOMINAL= ".$programmedresp->tolerance;
            //echo "  float value of result= ".floatval($result);
            
            if (floatval($response - $programmedresp->tolerance) <= floatval($result) && floatval($response + $programmedresp->tolerance) >= floatval($result)) {
                return 1;
            }

        // Tolerance relative
        } else if (floatval($response * (1 - $programmedresp->tolerance)) < floatval($result) && floatval($response * (1 + $programmedresp->tolerance)) > floatval($result)) {
            echo "<br>PROGRAMMEDRESP_TOLERANCE_RELATIVE";
            return 1;
        }

        return 0;
    }

}
