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
 * @copyright 2016 Gerard Cuello (gerard.urv@gmail.com)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Generates the output for programmedresp questions.
 *
 * @copyright 2016 Gerard Cuello (gerard.urv@gmail.com)

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_programmedresp_renderer extends qtype_renderer {

    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the quetsion text, and the controls for students to
     * input their answers. Some question types also embed bits of feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
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
        $responses = array();
        foreach ($question->expectedresps as $expectedresp) {
            $respkey = $question->field($expectedresp->returnkey);
            $responses[$respkey] = $qa->get_last_qt_var($respkey);
            $name = $qa->get_qt_field_name($respkey);

            $inputattributes['name'] = $name;
            $inputattributes['value'] = $responses[$respkey];
            $inputattributes['id'] = $name;

            $hidden = '';
            if (!$options->readonly) {
                $hidden = html_writer::empty_tag('input', array(
                            'type' => 'hidden',
                            'name' => $name,
                            'value' => 0,
                ));
            }

            $class = 'r' . ($expectedresp->returnkey % 2);
            if ($options->correctness) {
                $fraction = $question->get_matching_answer($responses[$respkey],
                        $expectedresp->returnkey);
                if(!$fraction){
                    $fraction = 0;
                }
                $feedbackimg[$expectedresp->returnkey] = $this->feedback_image($fraction);
                $inputattributes['class'] = $this->feedback_class($fraction);
            } else {
                $feedbackimg[$expectedresp->returnkey] = '';
            }
            $classes[$expectedresp->returnkey] = $class;

            $inputs[$expectedresp->returnkey] = html_writer::tag('bel', $expectedresp->label,
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
                    array('class' => $classes[$key])) . PHP_EOL;
        }
        $result .= html_writer::end_tag('div'); // answer
        $result .= html_writer::end_tag('div'); // ablock

        if ($qa->get_state() == question_state::$invalid) {
            $errorvalidate = $question->get_validation_error($responses);
            $result .= html_writer::nonempty_tag('div', $errorvalidate,
                    array('class' => 'validationerror'));
        }

        return $result;
    }

    /**
     * Gereate an automatic description of the correct response to this question.
     * Not all question types can do this. If it is not possible, this method
     * should just return an empty string.
     *
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    public function correct_response(question_attempt $qa) {

        $question = $qa->get_question();
        $answers = $question->answers;
        $tolerance = (float) $question->tolerance;
        $numdecimals =explode('.',$tolerance);

        $correctans = array();
        foreach ($question->expectedresps as $expectedresp) {
            $curranswer = $answers[$expectedresp->returnkey]->answer;
            if (is_numeric($curranswer)) {
                $curranswer = round($curranswer, strlen($numdecimals[1]));
            }
            $correctans[] = $curranswer;
        }
        if (!empty($correctans)) {
            return get_string('correctansweris', 'qtype_multichoice', implode(', ', $correctans));
        }

        return '';
    }

    public function specific_feedback(question_attempt $qa) {
        // TODO.
        return '';
    }

}
