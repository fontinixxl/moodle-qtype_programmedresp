<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');

class qtype_programmedresp extends question_type {

    public $programmedrespfields = array('programmedrespfid', 'tolerancetype', 'tolerance');
    public $exportvarfields = array('varname', 'nvalues', 'maximum', 'minimum', 'valueincrement');
    public $exportconcatvarfields = array('origin', 'name', 'vars');
    public $exportargfields = array('argkey', 'type', 'value');
    public $exportrespfields = array('returnkey', 'label');
    public $exportfunctionfields = array('programmedrespfcatid', 'name', 'description', 'nreturns', 'params', 'results');

    public function name() {
        return 'programmedresp';
    }

    public function extra_question_fields() {
        return array('qtype_programmedresp', 'programmedrespfid', 'tolerancetype', 'tolerance');
    }

    function questionid_column_name() {
        return 'question';
    }

    /**
     * Saves (creates or updates) a question.
     *
     * Given some question info and some data about the answers
     * this function parses, organises and saves the question
     * It is used by {@link question.php} when saving new data from
     * a form, and also by {@link import.php} when importing questions
     * This function in turn calls {@link save_question_options}
     * to save question-type specific data.
     *
     * Whether we are saving a new question or updating an existing one can be
     * determined by testing !empty($question->id). If it is not empty, we are updating.
     *
     * The question will be saved in category $form->category.
     *
     * @param object $question the question object which should be updated. For a
     *      new question will be mostly empty.
     * @param object $form the object containing the information to save, as if
     *      from the question editing form.
     * @param object $course not really used any more.
     * @return object On success, return the new question object. On failure,
     *       return an object as follows. If the error object has an errors field,
     *       display that as an error message. Otherwise, the editing form will be
     *       redisplayed with validation errors, from validation_errors field, which
     *       is itself an object, shown next to the form fields. (I don't think this
     *       is accurate any more.)
     */
    public function save_question_options($question) {
        global $DB;
        //print_r($question);
        //debugging('save question options FUNCTION', DEBUG_DEVELOPER);

        // It does't return the inserted/updated qtype_programmedresp->id
        parent::save_question_options($question);


        $programmedresp = $DB->get_record('qtype_programmedresp', array('question' => $question->id));

        // If we are updating, they will be reinserted
        $DB->delete_records('qtype_programmedresp_resp', array('programmedrespid' => $programmedresp->id));

        if (empty($question->vars) || empty($question->args)) {
            $result = $this->save_question_options_from_form($question, $programmedresp);
        } else {
            //segons crec, aquesta opcio es x quan importem preguntes d'altres cursos 
            // $result = $this->save_question_options_from_questiondata($question, $programmedresp);
        }

        // Rollback changes
        if (!$result) {
            $this->delete_question($question->id);
            //return false;
        }
    }

    /**
     * Loads the question type specific options for the question.
     *
     * This function loads any question type specific options for the
     * question from the database into the question object. This information
     * is placed in the $question->options field. A question type is
     * free, however, to decide on a internal structure of the options field.
     * @return bool            Indicates success or failure.
     * @param object $question The question object for the question. This object
     *                         should be updated to include the question type
     *                         specific information (it is passed by reference).
     */
    public function get_question_options($question) {
        global $DB;
        debugging("QUETIONTYPE:get_question_options");

        $question->options->programmedresp = $DB->get_record('qtype_programmedresp', array('question' => $question->id));
        if (!$question->options->programmedresp) {
            return false;
        }
        $question->options->vars = $DB->get_records('qtype_programmedresp_var', array('programmedrespid' => $question->options->programmedresp->id));
        $question->options->args = $DB->get_records('qtype_programmedresp_arg', array('programmedrespid' => $question->options->programmedresp->id));
        $question->options->resps = $DB->get_records('qtype_programmedresp_resp', array('programmedrespid' => $question->options->programmedresp->id), 'returnkey ASC', 'returnkey, label');
        $question->options->concatvars = $DB->get_records_select('qtype_programmedresp_conc', "origin = 'question' AND instanceid = '{$question->options->programmedresp->id}'");
        $question->options->function = $DB->get_record('qtype_programmedresp_f', array('id' => $question->options->programmedresp->programmedrespfid));
        
        parent::get_question_options($question);

    }

    /**
     * Initialise the common question_definition fields.
     * @param question_definition $question the question_definition we are creating.
     * @param object $questiondata the question data loaded from the database.=> la de get_question_option()
     */
    protected function initialise_question_instance(question_definition $question, $questiondata) {
        debugging("initialise_question_instance");
        parent::initialise_question_instance($question, $questiondata);
        $question->resps = $questiondata->options->resps;
        $question->options = $questiondata->options;
        
        $question->answers = array();
        foreach ($question->resps as $resp) {
            $question->answers[$resp->returnkey] = new question_answer($resp->returnkey, '',
                    0, '', 1);
        }
    }

    /**
     * Gets the data to insert/update from the _POST request
     * @param $question
     * @param $programmedresp
     */
    function save_question_options_from_form($question, $programmedresp) {
        //debugging('save question options from form FUNCTION', DEBUG_DEVELOPER);
        global $DB;
        $argtypesmapping = programmedresp_get_argtypes_mapping();
        $i = 0;
        foreach ($_POST as $varname => $value) {

            // Insert var
            if (substr($varname, 0, 4) == 'var_') {

                $vardata = explode('_', $varname);
                $vars[$vardata[2]]->{$vardata[1]} = clean_param($value, PARAM_NUMBER);   // integer or float
                // Insert a function argument
            } else if (substr($varname, 0, 8) == 'argtype_') {

                $args[$i]->programmedrespid = $programmedresp->id;
                $args[$i]->argkey = intval(substr($varname, 8));     // integer
                $args[$i]->type = intval($value);


                // There are a form element for each var type (fixed, variable, concat, guidedquiz)
                // $argvalue contains the value of the selected element
                $argvalue = $_POST[$argtypesmapping[intval($value)] . "_" . $args[$i]->argkey];
                //debugging($argvalue);
                
                $args[$i]->value = clean_param($argvalue, PARAM_TEXT);  // integer or float if it's fixed or a varname
                debugging("args[.$i.]->value = ".$args[$i]->value);
                
                $i++;

                // Insert a function response
            } else if (substr($varname, 0, 5) == 'resp_') {

                $resp->programmedrespid = $programmedresp->id;
                $resp->returnkey = intval(substr($varname, 5));   // $varname must be something like resp_0
                $resp->label = clean_param($value, PARAM_TEXT);
                if (!$DB->insert_record('qtype_programmedresp_resp', $resp)) {
                    print_error('errordb', 'qtype_programmedresp');
                }

                
                //
            }
        }

        // Delete any left over old answer records.
        if (!empty($vars)) {
            foreach ($vars as $varname => $var) {
                $var->programmedrespid = $programmedresp->id;
                $var->varname = $varname;

                // Update
                if ($var->id = $DB->get_field('qtype_programmedresp_var', 'id', array('programmedrespid' => $var->programmedrespid, 'varname' => $var->varname))) {

                    if (!$DB->update_record('qtype_programmedresp_var', $var)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }

                    // Insert
                } else {
                    if (!$vars[$varname]->id = $DB->insert_record('qtype_programmedresp_var', $var)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }
                }
            }
        }


        if ($args) {
            foreach ($args as $arg) {

                // If it's a variable we must look for the qtype_programmedresp_var identifier
                if ($arg->type == PROGRAMMEDRESP_ARG_VARIABLE) {
                    if (!isset($vars[$arg->value])) {
                        print_error('errorcantfindvar', 'qtype_programmedresp', $arg->value);
                    }
                    $arg->value = $vars[$arg->value]->id;
                }

                // If it's a concat var we must serialize the concatvar_N param
                if ($arg->type == PROGRAMMEDRESP_ARG_CONCAT) {

                    $concatnum = intval(substr($arg->value, 10));
                    if (!$concatvalues = optional_param('concatvar_' . $concatnum, false, PARAM_ALPHANUM)) {
                        print_error('errorcantfindvar', 'qtype_programmedresp', $arg->value);
                    }else{
                        if (!$concreadablename = optional_param('nconcatvar_' . $concatnum, false, PARAM_ALPHANUM)) {
                            print_error('errorcantfindvar', 'qtype_programmedresp', $arg->value);
                        }
                    }
                    debugging("concatvalues".print_r($concatvalues));
                    debugging("concatvar_readablename = ".$concreadablename);
                    
                    // Inserting/Updating the new concat var
                    $concatvarname = 'concatvar_' . $concatnum;
                    if (!$concatobj = $DB->get_record('qtype_programmedresp_conc', array('origin' => 'question', 'instanceid' => $programmedresp->id, 'name' => $concatvarname))) {
                        $concatobj = new stdClass();
                        $concatobj->origin = 'question';
                        $concatobj->instanceid = $programmedresp->id;
                        $concatobj->name = $concatvarname;
                        $concatobj->readablename = $concreadablename;
                        $concatobj->vars = programmedresp_serialize($concatvalues);
                        if (!$concatobj->id = $DB->insert_record('qtype_programmedresp_conc', $concatobj)) {
                            print_error('errordb', 'qtype_programmedresp');
                        }
                    } else {
                        $concatobj->vars = programmedresp_serialize($concatvalues);
                        $DB->update_record('qtype_programmedresp_conc', $concatobj);
                    }

                    $arg->value = $concatobj->id;
                }

                // Update
                if ($arg->id = $DB->get_field('qtype_programmedresp_arg', 'id', array('programmedrespid' => $arg->programmedrespid, 'argkey' => $arg->argkey))) {

                    if (!$DB->update_record('qtype_programmedresp_arg', $arg)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }

                    // Insert
                } else {
                    if (!$DB->insert_record('qtype_programmedresp_arg', $arg)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }
                }
            }
        }

        return true;
    }

    public function delete_question($questionid, $contextid) {
        global $DB;
        $programmedresp = $DB->get_record('qtype_programmedresp', array('question' => $questionid));
        if (!$programmedresp) {
            return false;
        }

        $DB->delete_records('qtype_programmedresp_arg', array('programmedrespid' => $programmedresp->id));
        $DB->delete_records('qtype_programmedresp_resp', array('programmedrespid' => $programmedresp->id));

        $vars = $DB->get_records('qtype_programmedresp_var', array('programmedrespid' => $programmedresp->id));
        if ($vars) {
            foreach ($vars as $var) {
                $DB->delete_records('qtype_programmedresp_val', array('programmedrespvarid' => $var->id));
            }
        }

        $DB->delete_records('qtype_programmedresp_var', array('programmedrespid' => $programmedresp->id));
        $DB->delete_records('qtype_programmedresp_conc', array('origin' => 'question', 'instanceid' => $programmedresp->id));
        $DB->delete_records('qtype_programmedresp', array('question' => $questionid));

        parent::delete_question($questionid, $contextid);
    }

    /**
     * Gets the data to insert from the $question object (petitions from import...)
     * @param $question
     * @param $programmedresp
     */
    public function save_question_options_from_questiondata($question, $programmedresp) {

        $varmap = array();   // Maintains the varname -> varid relation
//        if (empty($question->vars) || empty($question->args) || empty($question->resps)) {
//            return false;
//        }
        // Vars
        // GERARD: totes aquestes es guarden en el save_data() del edit_form
        if (!empty($question->vars)) {
            foreach ($question->vars as $vardata) {

                $var->programmedrespid = $programmedresp->id;
                $var->varname = $vardata->varname;
                $var->nvalues = $vardata->nvalues;
                $var->maximum = $vardata->maximum;
                $var->minimum = $vardata->minimum;
                $var->valueincrement = $vardata->valueincrement;
                if (!$varmap[$vardata->varname] = insert_record('qtype_programmedresp_var', $var)) {
                    print_error('errordb', 'qtype_programmedresp');
                }
            }
        }

        // Concat vars
        if (!empty($question->concatvars)) {
            foreach ($question->concatvars as $vardata) {

                $var->origin = $vardata->name;
                $var->instanceid = $programmedresp->id;
                $var->name = $vardata->name;
                $var->vars = $vardata->vars;
                if (!$concatvarmap[$vardata->name] = insert_record('qtype_programmedresp_conc', $var)) {
                    print_error('errordb', 'qtype_programmedresp');
                }
            }
        }

        // Args
        if (!empty($question->args)) {
            foreach ($question->args as $argdata) {

                $arg->programmedrespid = $programmedresp->id;
                $arg->argkey = $argdata->argkey;
                $arg->type = $argdata->type;

                // Getting the var id
                if ($argdata->type == PROGRAMMEDRESP_ARG_VARIABLE) {
                    $argdata->value = $varmap[$argdata->value];

                    // Getting the concat var id
                } else if ($argdata->type == PROGRAMMEDRESP_ARG_CONCAT) {
                    $argdata->value = $concatvarmap[$argdata->value];
                }

                $arg->value = $argdata->value;
                if (!$DB->insert_record('qtype_programmedresp_arg', $arg)) {
                    print_error('errordb', 'qtype_programmedresp');
                }
            }
        }

        // Resps
        if (!empty($question->resps)) {
            foreach ($question->resps as $respdata) {

                $resp->programmedrespid = $programmedresp->id;
                $resp->returnkey = $respdata->returnkey;
                $resp->label = $respdata->label;
                if (!$DB->insert_record('qtype_programmedresp_resp', $resp)) {
                    print_error('errordb', 'qtype_programmedresp');
                }
            }
        }

        return true;
    }

    public function get_possible_responses($questiondata) {
        return array();
    }

}
