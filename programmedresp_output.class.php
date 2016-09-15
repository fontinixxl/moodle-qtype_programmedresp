<?php

/**
 * Manages the moodleform and ajax shared outputs
 *
 * @copyright 2010 David Monllaó <david.monllao@urv.cat>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package qtype_programmedresp
 */
class prgrammedresp_output {

    var $mform;

    function prgrammedresp_output(&$mform) {
        $this->mform = $mform;
    }


    function add_concat_var($name, $vars, $values = false, $return = false, $readablename = false) {

        if (!$readablename) {
            $readablename = $name;
        }

        $concatdiv = '<input type="text" id="n' . $name . '" name="n' . $name . '" value="' . $readablename . '" ></br>';
        $concatdiv.= '<select id="' . $name . '" name="' . $name . '[]" multiple="multiple">';

        // Marking the selected vars
        foreach ($vars as $var) {
            $selectedstr = '';
            if ($values) {
                foreach ($values as $concatvar) {
                    if ($var == $concatvar) {
                        $selectedstr = 'selected="selected"';
                    }
                }
            }
            $concatdiv.= '<option value="' . $var . '" ' . $selectedstr . '>' . $var . '</option>';
        }
        $concatdiv.= '</select>';
        $concatdiv.= '&nbsp;&nbsp;<input type="button" onclick="confirm_concat_var(\'' . $name . '\');" value="' . get_string('confirmconcatvar', 'qtype_programmedresp') . '"/>';
        $concatdiv.= '&nbsp;<input type="button" onclick="cancel_concat_var(\'' . $name . '\');" value="' . get_string('cancelconcatvar', 'qtype_programmedresp') . '" />';
        $concatdiv.= '<br/><br/>';

        if ($return) {
            return $concatdiv;
        }

        echo $concatdiv;
    }

    /**
     * Prints form elements for the question vars based on question->questiontext
     * @param string $questiontext
     * @param array $args To restore the already added concat vars
     * @param boolean $displayfunctionbutton True for programmedresp, false for guidedquiz
     * @param array $quizconcatvars The already created guided quiz concat vars
     */
    function display_vars($questiontext = false, $args = false, $quizconcatvars = false) {

        // If there aren't vars just notify it
        if (!$vars = programmedresp_get_question_vars($questiontext)) {
            $this->print_form_htmlraw('<span class="programmedresp_novars">' . get_string('novars', 'qtype_programmedresp') . '</span>');
        }

        // The variables fields
        $fields = programmedresp_get_var_fields();
        $varattrs['onblur'] = 'check_numeric(this, \'' . addslashes(get_string("nonumeric", "qtype_programmedresp")) . '\');';

        // Selectors for each match
        if ($vars) {
            foreach ($vars as $varname) {

                $this->print_form_title('<strong>' . get_string("var", "qtype_programmedresp") . ' ' . $varname . '</strong>');

                foreach ($fields as $fieldkey => $title) {
                    $this->print_form_text($title, 'var_' . $fieldkey . '_' . $varname, '', $varattrs);
                }
                $this->print_form_spacer();
            }

            //Concat vars
            $concatdiv = '<div id="id_concatvars">';

            // Restoring the concatenated vars
            if ($quizconcatvars) {
                foreach ($quizconcatvars as $concatdata) {
                    $concatdata->values = programmedresp_unserialize($concatdata->vars);
                    $concatdiv.= $this->add_concat_var($concatdata->name, $vars, $concatdata->values, true, $concatdata->readablename);
                }
            }

            $concatdiv.= '</div>';
            $this->print_form_html($concatdiv);
            $this->print_form_html('<a href="#" onclick="add_concat_var();return false;">' . get_string("addconcatvar", "qtype_programmedresp") . '</a><br/>');
        }

        // TODO: Add a check_maximum and check_minimum to ensure max > min
    }

    /**
     * Prints a select with the category functions list
     * @param integer $categoryid
     */
    function display_functionslist($categoryid = false) {
        global $DB;
        // Retrieving category functions
        if ($categoryid) {
            $functions = $DB->get_records('qtype_programmedresp_f', array('programmedrespfcatid' => $categoryid));
            if (!$functions) {
                $this->print_form_html(get_string('errornofunctions', 'qtype_programmedresp'));
            }
        }

        // Functions
        //$options = array('0' => ' (' . get_string("selectfunction", "qtype_programmedresp") . ') ');
        if (!empty($functions)) {
            foreach ($functions as $function) {
                $options[$function->id] = $function->name;
            }
        }

        $attrs['onchange'] = 'return display_args(this);';
        $this->print_form_select(get_string('function', 'qtype_programmedresp'), 'programmedrespfid', $options, $attrs, true);
    }

    /**
     * Prints form elements to assign vars / values to the selected function arguments
     *
     * @param type $functionid
     * @param type $questiontext
     * @param type $args
     * @param type $vars
     * @param int $quizid a valid quiz id or '-1' to indicate we aren't in a quiz context.
     * @return boolean
     */
    function display_args($functionid, $questiontext = false, $args = false, $vars = false, $quizid) {
        global $DB;

        if (!$functionid) {
            die();
        }

        // Function data
        $functiondata = $DB->get_record('qtype_programmedresp_f', array('id' => $functionid));
        $functiondata->params = programmedresp_unserialize($functiondata->params);
        $functiondata->results = programmedresp_unserialize($functiondata->results);
        if (!is_array($functiondata->params) || !is_array($functiondata->results)) {
            $this->print_form_htmlraw('<span class="error">' . get_string('errorparsingfunctiondata', 'qtype_programmedresp') . '</span>');
            return false;
        }

        // Get the questiontext vars to fill the variables selector
        $questiontextvars = programmedresp_get_question_vars($questiontext);

        // Concatenated vars (if it's a new insertion getting from _GET if not from $args array)
        $concatvars = programmedresp_get_concat_vars($args);

        // Map arg type id => arg type name (fixed, variable or linker)
        $argtypes = programmedresp_get_argtypes_mapping();

        // Get the linkerdesc vars only if a valid quizid is given
        $linkervars = programmedresp_get_linkerdesc_vars($quizid);

        $this->print_form_htmlraw('<br/><div class="programmedresp_functiondescription">' . stripslashes(format_text($functiondata->description, FORMAT_MOODLE)) . '</div>');

        // Assign arguments
        $argstitle = '<strong>' . get_string('functionarguments', 'qtype_programmedresp') . '</strong>';
        $displaylink = '<a href="#" onclick="return functionsection_visible();">' . get_string("refresh", "qtype_programmedresp") . '</a>';
        $argstitle .= '<br>( ' . $displaylink . ' )';
        $this->print_form_title($argstitle);
        foreach ($functiondata->params as $key => $param) {

            // Various param types
            if (strpos($param->type, '|') != false) {
                $paramtypes = explode('|', $param->type);
                foreach ($paramtypes as $key => $paramtype) {
                    $paramtypes[$key] = get_string('paramtype' . $paramtype, 'qtype_programmedresp');
                }
                $paramsstring = implode(' ' . get_string('or', 'qtype_programmedresp') . ' ', $paramtypes);

                // Only one param type
            } else {
                $paramsstring = get_string("paramtype" . $param->type, "qtype_programmedresp");
            }

            // Argument description
            $this->print_form_htmlraw('<div class="fitem"><div class="fitemtitle">' . format_text($param->description, FORMAT_MOODLE) . ' (' . get_string("type", "qtype_programmedresp") . ': ' . $paramsstring . ')</div>');

            // Argument value type
            $paramelement = '<select name="argtype_' . $key . '" onchange="change_argument_type(this, \'' . $key . '\');">';
            foreach ($argtypes as $argid => $argname) {

                if (!$questiontextvars && ($argname == 'variable' || $argname == 'concat')) {
                    continue;
                }

                // If there are previous data and it is the selected argument type: selected
                $selectedstr = '';
                if ($args) {
                    if (($args[$key]->origin == 'linker' && $argid == PROGRAMMEDRESP_ARG_LINKER) ||
                        ($args[$key]->origin == 'local' && $args[$key]->type == $argid)) {
                        $selectedstr = 'selected="selected"';
                    }
                }

                $paramelement.= '<option value="' . $argid . '" ' . $selectedstr . '>' . get_string('arg' . $argname, 'qtype_programmedresp') . '</option>';
            }
            $paramelement.= '</select>&nbsp;';


            // Argument value type dependencies
            $fixedvalue = '';
            $variablevalue = '';
            $concatvalue = '';
            $linkervalue = '';
            $fixedclass = 'hidden_arg';
            $variableclass = 'hidden_arg';
            $linkerclass = 'hidden_arg';
            $concatclass = 'hidden_arg';

            // If it's a new insertion we show fixed
            if (!$args) {
                $fixedclass = '';
            } else {
                // TODO: Refactor first condition with previous condition
                if ($args[$key]->origin == 'local' && $args[$key]->type == PROGRAMMEDRESP_ARG_FIXED) {
                    $fixedvalue = $args[$key]->value;
                    $fixedclass = '';
                } else if ($args[$key]->origin == 'local' && $args[$key]->type == PROGRAMMEDRESP_ARG_VARIABLE) {
                    $variablevalue = $vars[$args[$key]->value]->varname;
                    $variableclass = '';
                } else if ($args[$key]->origin == 'local' && $args[$key]->type == PROGRAMMEDRESP_ARG_CONCAT) {
                    $concatdata = programmedresp_get_concatvar_data($args[$key]->value);
                    $concatvalue = $concatdata->name;
                    $concatclass = '';
                } else if ($args[$key]->origin == 'linker' && $linkervars) {

                    $linkerclass = '';
                    // Get the previous selected linker var if it was set.

                    // Assign the previous selected linkervar if it was assing.
                    if (isset($args[$key]->type) && isset($args[$key]->value)) {
                        if (!$linkervalue = $linkervars[$argtypes[$args[$key]->type]. '_' . $args[$key]->value]) {
                            $linkerclass = "redtext";
                        }

                    }
                }
            }

            // Fixed
            $paramelement.= '<input type="text" name="fixed_' . $key . '" id="id_argument_fixed_' . $key . '" value="' . $fixedvalue . '" class="' . $fixedclass . '"/>';

            // Variables
            if ($questiontextvars) {
                $paramelement.= '<select name="variable_' . $key . '" id="id_argument_variable_' . $key . '" class="' . $variableclass . '">';
                foreach ($questiontextvars as $varname) {

                    $selectedstr = '';
                    if ($variablevalue == $varname) {
                        $selectedstr = 'selected="selected"';
                    }
                    $paramelement.= '<option value="' . $varname . '" ' . $selectedstr . '>' . get_string("var", "qtype_programmedresp") . ' ' . $varname . '</option>';
                }
                $paramelement.= '</select>';

                // Concat vars
                $paramelement.= '<select name="concat_' . $key . '" id="id_argument_concat_' . $key . '" class="' . $concatclass . '">';
                foreach ($concatvars as $varname => $varvalue) {

                    $selectedstr = '';
                    if ($concatvalue == $varname) {
                        $selectedstr = 'selected="selected"';
                    }
                    $paramelement.= '<option value="' . $varname . '" ' . $selectedstr . '>' . get_string("var", "qtype_programmedresp") . ' ' . $varvalue . '</option>';
                }
                $paramelement.= '</select>';
            }

            // Linkerdesc variables
            if ($linkervars) {
                $paramelement.= '<select name="linker_' . $key . '" id="id_argument_linker_' . $key . '" class="' . $linkerclass . '">';
                // It can be empty when we fill linker arg from outside of quiz context.
                if (empty($linkervalue)) {
                    // TODO: put hardcoded text to lang folder.
                    $paramelement.= "<option disabled selected value> -- select an option -- </option>";
                }
                foreach ($linkervars as $varid => $varname) {
                    $selectedstr = '';
                    if ($linkervalue == $varname) {
                        $selectedstr = 'selected="selected"';
                    }
                    $paramelement.= '<option value="' . $varid . '" ' . $selectedstr . '>' . $varname . '</option>';
                }
                $paramelement.= '</select>';
            } else {
                // TODO: If we are inside a quiz context without linkerdesc question,
                // we should notify that there won't be linker vars.
            }

            // Review if this span is already needed.
            $paramelement.= '<span  id="id_argument_linker_' . $key . '" class="' . $linkerclass . '"></span><input type="hidden" name="linker' . $key . '" value=""/>';
            $this->print_form_htmlraw('<div class="felement fselect">' . $paramelement . '</div></div>');
        }


        // To assign labels
        $this->print_form_htmlraw('<br/>');

        // Link to show the labels edition elements
        $this->print_form_htmlraw('<div id="id_responselabelslink">');
        $displayresponselabelslink = '<a href="#" onclick="return display_responselabels();">' . get_string('editresponselabels', 'qtype_programmedresp') . '</a>';
        $this->print_form_html($displayresponselabelslink);
        $this->print_form_htmlraw('</div>');

        // Hidden by default
        $this->print_form_htmlraw('<div id="id_responseslabels">');
        $this->print_form_title('<strong>' . get_string('questionresultslabels', 'qtype_programmedresp') . '</strong>');
        for ($i = 0; $i < $functiondata->nreturns; $i++) {

            if (!empty($functiondata->results[$i])) {
                $value = str_replace('"', '&quot;', $functiondata->results[$i]);
            } else {
                $value = '';
            }

            $this->print_form_text(get_string("response", "qtype_programmedresp") . ' ' . ($i + 1), 'resp_' . $i, $value);
        }
        $this->print_form_htmlraw('</div>');
    }

    function print_form_title($title) {
        $this->mform->addElement('html', '<div class="fitem"><div class="fitemtitle">' . $title . '</div></div>');
    }

    function print_form_button($title, $elementname, $attrs = false) {
        $this->mform->addElement('button', $elementname, $title, $attrs);
    }

    function print_form_text($title, $elementname, $value = '', $attrs = false) {
        $this->mform->addElement('text', $elementname, $title, $attrs);
        $this->mform->setType($elementname, PARAM_FLOAT);
        $this->mform->setDefault($elementname, $value);
    }

    function print_form_html($text) {
        $text = '<div class="fitem"><div class="fitemtitle"></div><div class="felement">' . $text . '</div></div>';
        $this->mform->addElement('html', $text);
    }

    function print_form_htmlraw($text) {
        $this->mform->addElement('html', $text);
    }

    function print_form_select($title, $elementname, $options, $attrs = false, $required = false) {
        $this->mform->addElement('select', $elementname, $title, $options, $attrs);
        if ($required) {
            $this->mform->addRule($elementname, null, 'required', 'client');
        }
    }

    function print_form_spacer() {
        $this->mform->addElement('html', '<br/><br/>');
    }

}
