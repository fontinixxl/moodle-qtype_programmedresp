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
 * Constant vars and common functions for the programmed responses question type
 *
 * @copyright 2010 David Monllaó <david.monllao@urv.cat>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

define('PROGRAMMEDRESP_TOLERANCE_RELATIVE', 1);
define('PROGRAMMEDRESP_TOLERANCE_NOMINAL', 2);

define('PROGRAMMEDRESP_RESPONSEFORMAT_DECIMAL', 1);
define('PROGRAMMEDRESP_RESPONSEFORMAT_SIGNIFICATIVE', 2);

define('PROGRAMMEDRESP_ARG_FIXED', 0);
define('PROGRAMMEDRESP_ARG_VARIABLE', 1);
define('PROGRAMMEDRESP_ARG_LINKER', 2);
define('PROGRAMMEDRESP_ARG_CONCAT', 3);

define('NO_CONTEXT_QUIZ', -1);

/**
 * Checks file access for programmedresp questions.
 * @package  qtype_programmedresp
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool
 */
function qtype_programmedresp_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    question_pluginfile($course, $context, 'qtype_programmedresp', $filearea, $args, $forcedownload, $options);
}

/**
 * Returns the vars of the question text
 *
 * @param string $questiontext Optional
 * @return array Names of the variables found on the question text
 */
function programmedresp_get_question_vars($questiontext = false) {

    if (!$questiontext) {
        $questiontext = optional_param('questiontext', false, PARAM_RAW);
    }

    $pattern = '{\{\$[a-zA-Z0-9]*\}}';
    preg_match_all($pattern, $questiontext, $matches);

    if (empty($matches) || empty($matches[0])) {
        return false;
    }

    foreach ($matches[0] as $match) {
        $varname = substr($match, 2, (strlen($match) - 3));
        $vars[$varname] = $varname;
    }

    return $vars;
}

/**
 * Gets the concatenated vars
 *
 * @param array args Arguments data
 * @return array Concatenated vars with vars selected
 */
function programmedresp_get_concat_vars($args = false) {

    $concatvars = array();

    // If there are args filter by CONCAT type
    if ($args) {
        foreach ($args as $arg) {

            if (PROGRAMMEDRESP_ARG_CONCAT == $arg->type) {
                $concatdata = programmedresp_get_concatvar_data($arg->value);
                $concatvars[$concatdata->name] = $concatdata->readablename;
            }
        }

        // If there aren't args search on _GET
    } else {

        // TODO: Change this silly iteration
        // I hope 50 will be ok...
        for ($concatnum = 0; $concatnum < 50; $concatnum++) {

            $varname = 'concatvar_' . $concatnum;
            if ($concat = optional_param($varname, false, PARAM_ALPHANUM)) {
                if ($cancatname = optional_param('n' . $varname, false, PARAM_ALPHANUM)) {
                    $concatvars[$varname] = $cancatname;
                } else {
                    //echo ("no funciona la recepcio del nom de la variable concatenada");
                }
                //$concatvars[$varname] = $varname;
            }
        }
    }

    return $concatvars;
}

/**
 *
 * @param integer $id
 * @return The concat var data: name, referenced vars...
 */
function programmedresp_get_concatvar_data($id) {
    global $DB;
    $data = $DB->get_record('qtype_programmedresp_conc', array('id' => $id));
    if (!$data) {
        print_error('errornoconcatvar', 'qtype_programmedresp');
    }

    // "values" is a reserved DB field
    $data->values = programmedresp_unserialize($data->vars);
    unset($data->vars); // Just in case

    return $data;
}

/**
 * Gets the different attributes of the question text vars
 * @return array
 */
function programmedresp_get_var_fields() {

    return array('nvalues' => get_string('nvalues', 'qtype_programmedresp'),
        'minimum' => get_string('minimum', 'qtype_programmedresp'),
        'maximum' => get_string('maximum', 'qtype_programmedresp'),
        'valueincrement' => get_string('valueincrement', 'qtype_programmedresp'));
}

/**
 * Gets the argtype constant value against the arg type text
 * @return array
 */
function programmedresp_get_argtypes_mapping() {

    return array(PROGRAMMEDRESP_ARG_FIXED => 'fixed',
        PROGRAMMEDRESP_ARG_VARIABLE => 'variable',
        PROGRAMMEDRESP_ARG_CONCAT => 'concat',
        PROGRAMMEDRESP_ARG_LINKER => 'linker');
}

/**
 * Adds the function to the functions repository
 *
 * @pre Function code already verified
 * @param string $functioncode
 */
function programmedresp_add_repository_function($functioncode) {

    global $CFG;

    // TODO: Interoperability
    $linebreak = "\n";

    $file = $CFG->dataroot . '/qtype_programmedresp.php';

    programmedresp_check_datarootfile();

    $cleanfunctioncode = str_replace(chr(13), '', $functioncode);
    $cleanfunctioncode = str_replace(chr(10), '', $cleanfunctioncode);
    $cleanfunctioncode = str_replace('\r', '', $cleanfunctioncode);
    $cleanfunctioncode = str_replace('\n', '', $cleanfunctioncode);
    $cleanfunctioncode = str_replace('    ', ' ', $cleanfunctioncode);
    $cleanfunctioncode = str_replace('    ', ' ', $cleanfunctioncode);

    $fh = fopen($file, 'a+');

    fwrite($fh, $linebreak);
    fwrite($fh, $cleanfunctioncode);
    fwrite($fh, $linebreak);

    fclose($fh);
}

/**
 * @todo Improve!!!
 * @param unknown_type $parentid
 * @param unknown_type $catoptions
 * @param unknown_type $categories
 * @param unknown_type $nspaces
 */
function programmedresp_add_child_categories($parentid, &$catoptions, $categories, $nspaces = 2) {

    foreach ($categories as $key => $cat) {
        if ($cat->parent == $parentid && empty($catoptions[$cat->id])) {

            $spaces = '';
            $i = 0;
            while ($i < $nspaces) {
                $spaces.= '&nbsp;';
                $i++;
            }
            $catoptions[$cat->id] = $spaces . $cat->name;
            unset($categories[$key]);
            programmedresp_add_child_categories($cat->id, $catoptions, $categories, $nspaces + 2);
        }
    }
}

/**
 * Returns the function code
 * @todo Improve it!!!!
 * @param object $function programmedresp_f
 * @return string
 */
function programmedresp_get_function_code($functionname) {
    global $CFG;
    programmedresp_check_datarootfile();
    // Getting all the file
    if (!$filecode = file_get_contents($CFG->dataroot . '/qtype_programmedresp.php')) {
        print_error('errorcantaccessfile', 'qtype_programmedresp');
    }

    // Cleaning the code
    $filecode = str_replace('<?php', '', $filecode);
    while (strstr($filecode, '  ') != false) {
        $filecode = str_replace('  ', ' ', $filecode);
    }

    // The function file line must begin with this
    $searchedstring = 'function ' . $functionname . ' (';

    $parts = explode($searchedstring, $filecode);

    // Function doesn't exists
    if (count($parts) < 2) {
        return false;
    }

    // Look for the function end
    $functionend = 'function ';
    $partend = explode($functionend, $parts[1]);

    // The lats function
    if (count($partend) < 2) {
        $code = $searchedstring . ' ' . $parts[1];

    // Any function other than the last one
    } else {
        $code = $searchedstring . ' ' . $partend[0];
    }

    if (empty($code)) {
        return false;
    }

    return $code;
}

function programmedresp_serialize($var) {

    // Single value
    if (!is_object($var) && !is_array($var)) {
        return serialize(str_replace('"', '\"', $var));
    }

    if (is_object($var)) {
        foreach ($var as $attr => $value) {
            $var->$attr = str_replace('"', '\"', $value);
        }
    }

    if (is_array($var)) {
        foreach ($var as $key => $value) {
            if (!is_object($value)) {
                $var[$key] = str_replace('"', '\"', $value);
            } else {
                foreach ($value as $attr => $attrvalue) {
                    $var[$key]->$attr = str_replace('"', '\"', $attrvalue);
                }
            }
        }
    }

    $var = serialize($var);

    return $var;
}

function programmedresp_unserialize($var) {

    $var = str_replace('\"', '"', $var);
    $var = unserialize($var);

    return $var;
}

/**
 * Gets random value/s
 *
 * @param object $vardata
 * @return array Values array, array with size = 1 if there is a single value
 */
function programmedresp_get_random_value($vardata) {

    $values = array();
    for ($i = 0; $i < $vardata->nvalues; $i++) {

        if ($vardata->valueincrement == 0) {
            $values[] = $vardata->minimum;
            continue;
        }

        $differentincrements = round(($vardata->maximum - $vardata->minimum) / $vardata->valueincrement);
        $values[] = $vardata->minimum + (rand(0, $differentincrements) * $vardata->valueincrement);
    }

    return $values;
}

/**
 * It checks that the functions file exists
 */
function programmedresp_check_datarootfile() {

    global $CFG;

    $file = $CFG->dataroot . '/qtype_programmedresp.php';
    // Creating a new file
    if (!file_exists($file)) {
        if (!$fh = fopen($file, 'w')) {
            print_error('errornowritable', 'qtype_programmedresp');
        }
        fwrite($fh, '<?php');
        fwrite($fh, "\n");
        fclose($fh);
    }

    if (!is_writable($file)) {
        print_error('errornowritable', 'qtype_programmedresp');
    }
}

function programmedresp_check_base_functions_category() {

    global $CFG;

    $number = count_records('qtype_programmedresp_fcat');
    if ($number == 0) {

        $fcat->parent = 0;
        $fcat->name = get_string('pluginname', 'qtype_programmedresp');
        if (!$fcat->id = insert_record('qtype_programmedresp_fcat', $fcat)) {
            print_error('errordb', 'qtype_programmedresp');
        }

        return $fcat->id;
    }

    return 1;
}

/**
 * Returns the quiz
 * @param integer $usageid
 * @return integer quiz id
 */
function programmedresp_get_quizid($usageid) {
    global $DB;
    return $DB->get_field('quiz_attempts', 'quiz', array('uniqueid' => $usageid));
}

/**
 * TODO: It won't be needed.
 * Taking into account the dots
 * @param unknown_type $response
 * @return boolean
 */
function programmedresp_is_numeric($response) {

    if (!preg_match('/^-?[0-9]$/', $response) && !preg_match('/^-?[0-9]+\.[0-9]+$/', $response)) {
        return false;
    }

    return true;
}

/**
 * Uses the question tolerance to round the result
 * @param unknown_type $result
 * @param unknown_type $tolerance
 */
function programmedresp_round($result, $tolerance) {

    if (programmedresp_is_numeric($result) && strstr($tolerance, '.') != false) {
        $tmp = explode('.', $tolerance);
        $result = round($result, strlen($tmp[1]));
    }

    return $result;
}

/**
 * Store vars and concatvars from question text.
 *
 * @uses qtype_programmedresp questiontype.php
 * @uses qtype_linkerdesc questiontype.php
 * @param array $vars with all vars
 * @return array with new record id from programmedresp_var table.
 */
function programmedresp_store_vars($vars, $questionid) {
    global $DB;
    foreach ($vars as $varname => $var) {
        $var->question = $questionid;
        $var->varname = $varname;

        // Update
        if ($var->id = $DB->get_field('qtype_programmedresp_var', 'id', array('question' => $var->question, 'varname' => $var->varname))) {

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

    return $vars;
}

/**
 * Check if qtype linkerdesc is installed.
 *
 * @return bool
 */
function is_qtype_linkerdesc_installed() {
    return question_bank::is_qtype_installed('linkerdesc');
}

/**
 * Get all qtype_linkerdesc variables (vars and concatvars) from
 * the current quiz.
 *
 * @param int $quizid of the quiz where we are in.
 * @return false|array linkervars belonging to this quiz.
 */
function programmedresp_get_linkerdesc_vars($quizid) {
    global $DB;

    if ($quizid === NO_CONTEXT_QUIZ) {
        return false;
    }

    // 1-> Get all qtype_linkerdesc questions belonging to the current quiz.
    $qinquiz = $DB->get_records_sql("
        SELECT qs.questionid
          FROM {quiz_slots} as qs
          JOIN {question} q
            ON qs.questionid = q.id
         WHERE qs.quizid = ?
           AND q.qtype = 'linkerdesc'", array($quizid));

    // 2-> Get all linkerdesc variables.
    $linkervars = $DB->get_records_list('qtype_programmedresp_var', 'question', array_keys($qinquiz));
    // 3-> Get all linkerdesc concatenated variables.
    $linkerconcatvars = $DB->get_records_list('qtype_programmedresp_conc', 'question', array_keys($qinquiz));

    // 4-> Join vars and concatvars to be returned all together.
    $linkeroptions = array();
    foreach ($linkervars as $linkervar) {
        $linkeroptions['var_' . $linkervar->id] = $linkervar->varname . ' (' . get_string('vartypevar', 'qtype_programmedresp') . ')';
    }
    if ($linkerconcatvars) {
        foreach ($linkerconcatvars as $var) {
            $linkeroptions['concatvar_' . $var->id] = $var->readablename . ' (' . get_string('vartypeconcatvar', 'qtype_programmedresp') . ')';
        }
    }

    return $linkeroptions;
}

/**
 * Get the quiz id from cmid
 *
 * @param type $cmid
 * @return false|int false if it isn't a quiz module
 */
function programmedresp_getquiz_from_cm($cmid) {
    if (!$cmid) {
        $cmid = optional_param('cmid', 0, PARAM_INT);
    }

    list($module, $cmrec) = get_module_from_cmid($cmid);

    if ($cmrec->modname != 'quiz') {
        return false;
    }

    return $module->id;
}

/**
 * Prepare vars to be restored on edit form.
 *
 * @param array $vars loaded from DB
 * @return \stdClass
 */
function programmedresp_preprocess_vars($vars) {
    $toform = new stdClass();
    $varfields = programmedresp_get_var_fields();
    foreach ($vars as $var) {
        foreach (array_keys($varfields) as $varfield) {
            $fieldname = 'var_' . $varfield . '_' . $var->varname;
            // Little hack to remove useless zero digits from decimals
            // http://stackoverflow.com/questions/14531679/remove-useless-zero-digits-from-decimals-in-php
            $toform->{$fieldname} = $var->{$varfield} + 0;
        }
    }
    return $toform;
}
