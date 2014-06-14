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
 * programmedresp question renderer class.
 *
 * @package    qtype
 * @subpackage programmedresp
 * @copyright  THEYEAR YOURNAME (YOURCONTACTINFO)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/question/type/programmedresp/lib.php');      //??¿

/**
 * Generates the output for programmedresp questions.
 *
 * @copyright  THEYEAR YOURNAME (YOURCONTACTINFO)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_programmedresp_renderer extends qtype_renderer {
    /*
    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();
        //programmed response 
        $currentanswer = $qa->get_last_qt_var('answer');
        
        $questiontext = $question->format_questiontext($qa);
        $placeholder = false;
        if (preg_match('/_____+/', $questiontext, $matches)) {
            $placeholder = $matches[0];
        }
        $input = '**subq controls go in here**';

        if ($placeholder) {
            $questiontext = substr_replace($questiontext, $input,
                    strpos($questiontext, $placeholder), strlen($placeholder));
        }

        return $result;
    }
*/
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        
        global $DB;
        
        // Getting the module name from thispageurl
        //$modname = programmedresp_get_modname();
        
        $question = $qa->get_question();
        $currentanswer = $qa->get_last_qt_var('answer');
        echo "<br>Current answer = ".$currentanswer;
        $inputname = $qa->get_qt_field_name('answer');
        //$qtext = $qa->get_last_qt_var("_var_qtext");
        //echo '<br> render: qtext ' .$qtext;
        $inputattributes = array(
            'type' => 'text',
            'name' => $inputname,
            'value' => $currentanswer,
            'id' => $inputname,
            'size' => 80,
        );
        
        if ($options->readonly) {
            $inputattributes['readonly'] = 'readonly';
        }
        
        
        $programmedresp = $DB->get_record('qtype_programmedresp', array('question' => $question->id));
        if (!$programmedresp) {
            return false;
        }

        
        $feedbackimg = '';
        /*if($options->correctness){
            
            if($currentanswer){
                //print_error("entra!!");
                echo "<br>render.php,correctness options:";
                echo "<br>correct result=".$question->correctresult;
                echo "<br>current answer=".$currentanswer;
                $fraction = $question->test_programmed_response($question->correctresult,$currentanswer,$programmedresp);
                echo "<br>fraction = ".$fraction;
                $inputattributes['class'] = $this->feedback_class($fraction);
                $feedbackimg = $this->feedback_image($fraction);
            }
        }*/
        
        $questiontext = $question->format_questiontext($qa);
  
        $placeholder = false;
        if (preg_match('/_____+/', $questiontext, $matches)) {
            $placeholder = $matches[0];
            $inputattributes['size'] = round(strlen($placeholder) * 1.1);
        }
        $input = html_writer::empty_tag('input', $inputattributes) . $feedbackimg;

        if ($placeholder) {
            $inputinplace = html_writer::tag('label', get_string('answer'),
                    array('for' => $inputattributes['id'], 'class' => 'accesshide'));
            $inputinplace .= $input;
            $questiontext = substr_replace($questiontext, $inputinplace,
                    strpos($questiontext, $placeholder), strlen($placeholder));
        }
        
        $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));
        if (!$placeholder) {
            $result .= html_writer::start_tag('div', array('class' => 'ablock'));
            $result .= html_writer::tag('label', get_string('answer', 'qtype_programmedresp',
                    html_writer::tag('span', $input, array('class' => 'answer'))),
                    array('for' => $inputattributes['id']));
            $result .= html_writer::end_tag('div');
        }

        return $result;
    }
    public function specific_feedback(question_attempt $qa) {
        // TODO.
        return '';
    }

    public function correct_response(question_attempt $qa) {
        
        echo "<br>in render:correct_response()";
        $question = $qa->get_question();
        $answer = $question->get_correct_responses_without_round($qa->get_usage_id());
        if (!$answer) {
            return '';
        }
        return get_string('correctansweris', 'qtype_shortanswer', $answer[0]);
    }
}
