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

    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {

        $question = $qa->get_question();
        //$response = $question->get_response($qa);
        //$inputname = $qa->get_qt_field_name('answer');
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

            $inputattributes['name'] = $qa->get_qt_field_name('progrespkey' . $resp->returnkey);
            $inputattributes['value'] = $qa->get_last_qt_var('progrespkey' . $resp->returnkey);
            $inputattributes['id'] = $qa->get_qt_field_name('progrespkey' . $resp->returnkey);


            $hidden = '';
            if (!$options->readonly) {
                $hidden = html_writer::empty_tag('input', array(
                            'type' => 'hidden',
                            'name' => $inputattributes['name'],
                            'value' => 0,
                ));
            }
            $inputs[] = html_writer::tag('bel', $resp->label . ':', array('for' => $inputattributes['id'], 'class' => 'programmedresp')) .
                    html_writer::empty_tag('input', $inputattributes);
            //$inputs[] = $hidden . html_writer::empty_tag('input', $inputattributes) .
            //        html_writer::tag('bel', $resp->label, array('for' => $inputattributes['id']));
            $class = 'r' . ($resp->returnkey % 2);
            if ($options->correctness) {
                $is_right = $question->is_correct_answer($resp->returnkey, $qa);
                $feedbackimg[] = $this->feedback_image($is_right);
                $class .= ' ' . $this->feedback_class($is_right);
            } else {
                $feedbackimg[] = '';
            }
            $classes[] = $class;
        }

        $result = '';
        $result .= html_writer::tag('div', $question->format_questiontext($qa), array('class' => 'qtext'));

        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        //$result .= html_writer::tag('div', 'Introdueix les respostes', array('class' => 'prompt'));

        $result .= html_writer::start_tag('div', array('class' => 'answer'));
        foreach ($inputs as $key => $input) {
            $result .= html_writer::tag('div', $input . ' ' . $feedbackimg[$key], array('class' => $classes[$key])) . "\n";
        }
        $result .= html_writer::end_tag('div'); // answer

        $result .= html_writer::end_tag('div'); // ablock

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div', $question->get_validation_error($qa->get_last_qt_data()), array('class' => 'validationerror'));
        }

        return $result;
    }

    public function specific_feedback(question_attempt $qa) {
        // TODO.
        return '';
    }

    public function correct_response(question_attempt $qa) {
        debugging("in render:correct_response");
        $question = $qa->get_question();
        $right = array();
        $answers = $question->answers;
        foreach ($question->resps as $resp) {
            $right[] = $answers[$resp->returnkey]->answer;
            //$right[] = $question->make_html_inline($question->format_text($answers[$resp->returnkey]->answer, $answers[$resp->returnkey]->answerformat, $qa, 'question', 'answer', $resp->returnkey));
        }
        if (!empty($right)) {
            return get_string('correctansweris', 'qtype_multichoice', implode(', ', $right));
        }

        return '';
    }

}
