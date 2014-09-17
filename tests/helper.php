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
 * Test helpers for the programmed resp question type.
 *
 * @package    qtype
 * @subpackage numerical
 * @copyright  2011 Gerard Cuello
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Test helper class for the programmedresp question type.
 *
 * @copyright  2014 Gerard Cuello
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_programmedresp_test_helper extends question_test_helper {
    public function get_test_questions() {
        return array('average');
    }

    /**
     * Makes a programmedresp question with average function associated and
     * correct answer 6.25
     * answers with different feedback.
     * @return qtype_numerical_question
     */
    public function make_programmedresp_question_average(){
        global $CFG;
        require_once($CFG->dataroot . '/qtype_programmedresp.php');
        
        question_bank::load_question_definition_classes('programmedresp');
        $prog = new qtype_programmedresp_question();
        test_question_maker::initialise_a_question($prog);
        $prog->name = 'Test average';
        $prog->questiontext = 'Calculate the average with these values : 5,10,2,8 ';
        $prog->options->programmedresp = array(
            'tolerancetype' => PROGRAMMEDRESP_TOLERANCE_NOMINAL,
            'tolerance' => '0.01');
        //simulating the function get_correct_responses_with_round
        $exec = '$results = urv_mitjana_ind(array(5,10,2,8));';
        $exec = 'ob_start();' . $exec . 'ob_end_clean();';
        eval($exec);
        if (!is_array($results)) {
            $results = array($results);
        }
        $prog->answers = $results[0];
        
        return $prog;
    }
}