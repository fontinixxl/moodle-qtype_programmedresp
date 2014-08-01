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
 * Programmedresp question renderer class.
 *
 * @package    qtype_programmedresp
 * @copyright  2014 Gerard Cuello <gerard.urv@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/question/type/programmedresp/lib.php');      

/**
 * Generates the output for programmedresp questions.
 *
 * @copyright  2014 Gerard Cuello <gerard.urv@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_programmedresp_renderer extends qtype_renderer {

    public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

        $question = $qa->get_question();
        $inputattributes = array(
            'type' => 'text',
        );

        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }

        $inputs = array();
        $feedbackimg = array();
        $classes = array();
        foreach ($question->resps as $resp) {
            $inputattributes['name'] = $qa->get_qt_field_name('progrespkey'.
                    $resp->returnkey);
            $inputattributes['value'] = $qa->get_last_qt_var('progrespkey'.
                    $resp->returnkey);
            $inputattributes['id'] = $qa->get_qt_field_name('progrespkey'.
                    $resp->returnkey);

            $hidden = '';
            if (!$options->readonly) {
                $hidden = html_writer::empty_tag('input', array(
                            'type' => 'hidden',
                            'name' => $inputattributes['name'],
                            'value' => 0,
                ));
            }

            
            $class = 'r' . ($resp->returnkey % 2);
            if ($options->correctness) {
                $fraction = $question->is_correct_answer($resp->returnkey, $qa);
                if(!$fraction){
                    $fraction = 0;
                }
                $feedbackimg[$resp->returnkey] = $this->feedback_image($fraction);
                $inputattributes['class'] = $this->feedback_class($fraction);
                //$class .= ' ' . $this->feedback_class($fraction);
            } else {
                $feedbackimg[$resp->returnkey] = '';
            }
            $classes[$resp->returnkey] = $class;
            
            $inputs[$resp->returnkey] = html_writer::tag('bel', $resp->label,
                    array('for' => $inputattributes['id'], 'class' => 'programmedresp')).
                    html_writer::empty_tag('input', $inputattributes);
            
        }

        $result = '';
        $result .= html_writer::tag('div', $question->format_questiontext($qa),
                array('class' => 'qtext'));

        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= html_writer::start_tag('div', array('class' => 'answer'));
        foreach ($inputs as $key => $input) {
            $result .= html_writer::tag('div', $input . ' ' . $feedbackimg[$key],
                    array('class' => $classes[$key])) . "\n";
        }
        $result .= html_writer::end_tag('div'); // answer
        $result .= html_writer::end_tag('div'); // ablock
        if ($qa->get_state() == question_state::$invalid) {
            $errorvalidate = $question->get_validation_error($qa->get_last_qt_data());
            $result .= html_writer::nonempty_tag('div', $errorvalidate,
                    array('class' => 'validationerror'));
        }
        
        return $result;
    }

    public function specific_feedback(question_attempt $qa) {
        // TODO.
        return '';
    }

    public function correct_response(question_attempt $qa) {
        $question = $qa->get_question();
        $answers = $question->answers;
        $tolerance = (float) $question->options->programmedresp->tolerance;
        $numdecimals =explode('.',$tolerance);
        
        $right = array();
        foreach ($question->resps as $resp) {
            $right[] = round($answers[$resp->returnkey]->answer, strlen($numdecimals[1]));
        }
        if (!empty($right)) {
            return get_string('correctansweris', 'qtype_multichoice', implode(', ', $right));
        }

        return '';
    }

}
