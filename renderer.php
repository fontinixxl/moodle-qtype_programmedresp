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

    /*public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $question = $qa->get_question();
        $inputname = $qa->get_qt_field_name('answer');
        
        $inputattributes = array(
            'type' => 'text',
            'name' => $inputname,
        );
        
        if ($options->readonly) {
            $inputattributes['disabled'] = 'disabled';
        }
        
        $inputresponses = array();
        $classes = array();
        $i = 0;
        foreach ($question->resps as $resp) {
            $inputattributes['name'] = $qa->get_qt_field_name('progrespkey' . $resp->returnkey);
            $inputattributes['value'] = $qa->get_last_qt_var('answer');
            $inputattributes['id'] = $qa->get_qt_field_name('progrespkey' . $resp->returnkey);
            
            $hidden = '';
            if (!$options->readonly) {
                $hidden = html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'name' => $inputattributes['name'],
                    'value' => 0,
                ));
            }
            $inputresponses[] =html_writer::tag('bel',$resp->label,array('for' => $inputattributes['id'])) .
                    $hidden . html_writer::empty_tag('input', $inputattributes);
                    
            $class = 'r' . ($i % 2);
            $classes[] = $class;
            
            $i++;
        }
        $result = '';
        $result .= html_writer::tag('div', $question->format_questiontext($qa),
                array('class' => 'qtext'));
        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= html_writer::start_tag('div', array('class' => 'answer'));
        foreach ($inputattributes as $key => $re) {
            $result .= html_writer::tag('div', $re,
                    array('class' => $classes[$key])) . "\n";
            
        }
        $result .= html_writer::end_tag('div'); // answer

        $result .= html_writer::end_tag('div'); // ablock
        
        return $result;
    }*/
    /*
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {

        $question = $qa->get_question();

        $inputname = $qa->get_qt_field_name('answer');
        //returnkey is used as a key 0=>'resp1'; 1=>'limit inf' ....
        $entra = "no";
        foreach ($question->resps as $returnkey => $unused) {
            $entra = "si";
            //identifying correct answer
            $id = 'progrespkey' . $returnkey;
            //get currentanswer value
            $currentanswer = $qa->get_last_qt_var($id);

            $inputattributes[$returnkey] = array(
                'type' => 'text',
                'name' => $qa->get_qt_field_name($id), //per identificar unequivocament la resposta
                'value' => $currentanswer,
                'id' => $qa->get_qt_field_name($id),
                'size' => 10,
            );
            if ($options->readonly) {
                $inputattributes[$returnkey]['readonly'] = 'readonly';
                $hidden = '';
                $hidden = html_writer::empty_tag('input', array(
                            'type' => 'hidden',
                            'name' => $inputattributes[$returnkey]['name'],
                            'value' => 0,
                ));
            }
        }

        echo $entra;

        $questiontext = $question->format_questiontext($qa);

        $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));
        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $i = 0;
        foreach ($question->resps as $resp) {
            
            $input = html_writer::empty_tag('input', $inputattributes[$resp->returnkey]);

            //fiquem 2 inputs per fila, mirem si i és parell
            if ($i % 2 == 0) {
                $result .= html_writer::start_tag('p');
            }
            $result .= html_writer::tag('label', $resp->label.html_writer::tag('span', $hidden.$input, array('class' => 'answer')), array('for' => $inputattributes[$resp->returnkey]['id'], 'margin-right' => '10'));
            if ($i % 2 != 0) {
                $result .= html_writer::end_tag('p');
                $result .= html_writer::empty_tag('br');
            }

            $i++;
        }

        $result .= html_writer::end_tag('div');

        return $result;
    }*/
    
        public function formulation_and_controls(question_attempt $qa,
            question_display_options $options) {

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
        
        $classes = array();
        foreach ($question->resps as $resp) {
            
            $inputattributes['name'] = $qa->get_qt_field_name('progrespkey'.$resp->returnkey);
            $inputattributes['id'] = $qa->get_qt_field_name('progrespkey'.$resp->returnkey);

            $hidden = '';
            if (!$options->readonly) {
                $hidden = html_writer::empty_tag('input', array(
                    'type' => 'hidden',
                    'name' => $inputattributes['name'],
                    'value' => 0,
                ));
            }
            $inputs[] = $hidden . html_writer::empty_tag('input', $inputattributes) .
                    html_writer::tag('bel',$resp->label,array('for' => $inputattributes['id']));
            $class = 'r' . ($resp->returnkey % 2);
            $classes[] = $class;
        }

        $result = '';
        $result .= html_writer::tag('div', $question->format_questiontext($qa),
                array('class' => 'qtext'));

        $result .= html_writer::start_tag('div', array('class' => 'ablock'));
        $result .= html_writer::tag('div', 'Introdueix les respostes', array('class' => 'prompt'));

        $result .= html_writer::start_tag('div', array('class' => 'answer'));
        foreach ($inputs as $key => $radio) {
            $result .= html_writer::tag('div', $radio . ' ' ,
                    array('class' => $classes[$key])) . "\n";
        }
        $result .= html_writer::end_tag('div'); // answer

        $result .= html_writer::end_tag('div'); // ablock

        if ($qa->get_state() == question_state::$invalid) {
            $result .= html_writer::nonempty_tag('div',
                    $question->get_validation_error($qa->get_last_qt_data()),
                    array('class' => 'validationerror'));
        }

        return $result;
    }
    
    /* public function formulation_and_controls(question_attempt $qa, question_display_options $options) {

      global $DB;

      // Getting the module name from thispageurl
      //$modname = programmedresp_get_modname();

      $question = $qa->get_question();
      $currentanswer = $qa->get_last_qt_var('answer');
      echo "<br>Current answer = " . $currentanswer;
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

      $questiontext = $question->format_questiontext($qa);

      $placeholder = false;
      if (preg_match('/_____+/', $questiontext, $matches)) {
      $placeholder = $matches[0];
      $inputattributes['size'] = round(strlen($placeholder) * 1.1);
      }
      $input = html_writer::empty_tag('input', $inputattributes) . $feedbackimg;

      //aixo no se que fa exacatament ...
      if ($placeholder) {
      $inputinplace = html_writer::tag('label', get_string('answer'), array('for' => $inputattributes['id'], 'class' => 'accesshide'));
      $inputinplace .= $input;
      $questiontext = substr_replace($questiontext, $inputinplace, strpos($questiontext, $placeholder), strlen($placeholder));
      }

      $result = html_writer::tag('div', $questiontext, array('class' => 'qtext'));
      if (!$placeholder) {
      $result .= html_writer::start_tag('div', array('class' => 'ablock'));
      $result .= html_writer::tag('label', get_string('answer', 'qtype_programmedresp', html_writer::tag('span', $input, array('class' => 'answer'))), array('for' => $inputattributes['id']));
      $result .= html_writer::end_tag('div');
      }

      return $result;
      }
     * 
     */

    public function specific_feedback(question_attempt $qa) {
        // TODO.
        return '';
    }

    public function correct_response(question_attempt $qa) {
        debugging("in render:correct_response");
        $question = $qa->get_question();
        
        if (!$answer) {
            return '';
        }
        return get_string('correctansweris', 'qtype_shortanswer', $answer[0]);
    }

}
