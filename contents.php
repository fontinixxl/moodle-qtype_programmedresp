<?php

/**
 * Manages the different AJAX petitions
 *
 * @copyright 2010 David Monllaó <david.monllao@urv.cat>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package qtype_programmedresp
 */
require_once('../../../config.php');
require_once($CFG->dirroot . '/question/type/programmedresp/lib.php');
require_once($CFG->dirroot . '/question/type/programmedresp/programmedresp_output_ajax.class.php');
require_once($CFG->dirroot . '/question/editlib.php');



require_login_in_context(required_param('contextid', PARAM_INT));

$action = optional_param('action', false, PARAM_ALPHAEXT);
if (!$action) {
    die();
}

$outputmanager = new programmedresp_output_ajax($mform);
switch ($action) {

    // Question text vars
    case 'displayvars' :
        $outputmanager->display_vars(false, false);
        break;

    // Functions <select>
    case 'displayfunctionslist' :
        $categoryid = optional_param('categoryid', false, PARAM_INT);
        $outputmanager->display_functionslist($categoryid);
        break;

    // Function arguments
    case 'displayargs' :
        $functionid = optional_param('function', false, PARAM_INT);
        $quizid = required_param('quizid', PARAM_INT);
        $outputmanager->display_args($functionid, false, false, false, $quizid);
        break;

    case 'addconcatvar' :
        $concatnum = optional_param('concatnum', false, PARAM_INT);
        $vars = optional_param_array('vars', false, PARAM_ALPHANUM);
        $outputmanager->add_concat_var("concatvar_" . $concatnum, $vars, false, false);
        break;
}
