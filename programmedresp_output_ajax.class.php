<?php 

require_once($CFG->dirroot.'/question/type/programmedresp/programmedresp_output.class.php');

/**
 * programmedresp_output extension to simulate QuickForm on ajax petitions
 * 
 * The methods interface are the same
 *
 * @copyright 2010 David Monllaó <david.monllao@urv.cat>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package qtype_programmedresp
 */
class programmedresp_output_ajax extends prgrammedresp_output {

    /**
     * Prints a fitemtitle
     * @param $title
     */
    function print_form_title($title) {
        echo '<div class="fitem"><div class="fitemtitle">'.$title.'</div></div>';
    }
    
    /**
     * Prints a button (mform style) to execute an AJAX petition
     * 
     * @param string $title
     * @param string $elementname
     * @param string $attrs
     */
   function print_form_button($title, $elementname, $attrs) {
        
        echo '<div class="fitem"><div class="fitemtitle"></div>';
        echo '<div class="felement fbutton"><input name="'.$elementname.'" value="'.$title.'" type="button" id="id_'.$elementname.'" ';
        if ($attrs) {
            foreach ($attrs as $attrname => $attrvalue) {
                echo $attrname.'="'.$attrvalue.'"'; 
            }
        }
        echo '/>';
        echo '</div></div>';
    }
    
    /**
     * Prints an input text
     * 
     * @param string $title
     * @param string $elementname
     * @param string $value
     * @param array $attrs Form element other attributes
     */
    function print_form_text($title, $elementname, $value = '', $attrs = false) {
        
        echo '<div class="fitem">';
        echo '<div class="fitemtitle">'.$title.'</div>';
        echo '<div class="felement ftext"><input type="text" name="'.$elementname.'" id="id_'.$elementname.'" value="'.$value.'" ';
        if ($attrs) {
        	foreach ($attrs as $attrname => $attrvalue) {
        		echo $attrname.'="'.$attrvalue.'" ';
        	}
        }
        
        echo '/></div>';
    }
    
    
    /**
     * Prints html into an felement class
     * @param $text
     */
    function print_form_html($text) {
        echo  '<div class="fitem"><div class="fitemtitle"></div><div class="felement">'.$text.'</div></div>';
    }
    
    /**
     * Prints html
     * @param $text
     */
    function print_form_htmlraw($text) {
        echo $text;
    }
    
    /**
     * Prints a select
     * @param $title
     * @param $elementname
     * @param array $options Options
     * @param array $attrs Element attributes
     */
    function print_form_select($title, $elementname, $options, $attrs = false) {
        
        echo '<div class="fitem"><div class="fitemtitle">'.$title.'</div><div class="felement fselect">';
        echo '<select name="'.$elementname.'" id="id_'.$elementname.'" ';
        if ($attrs) {
            foreach ($attrs as $attrname => $attrvalue) {
                echo $attrname.'="'.$attrvalue.'"';
            }
        }
        echo '>';
        
        foreach ($options as $key => $option) {
            echo '<option value="'.$key.'">'.$option.'</option>';
        }
        
        echo '</select></div></div>';
    }

    function print_form_spacer() {
        echo '<br/><br/>';
    }
    
}
