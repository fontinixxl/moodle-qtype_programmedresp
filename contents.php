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

$action = optional_param('action', false, PARAM_ALPHAEXT);
if (!$action) {
    die();
}
global $DB;
$quizid = optional_param('quizid', 0, PARAM_INT);

$linkervars = array();
if ($quizid) {
    if ($qinquiz = $DB->get_records('qtype_linkerdescription', array('quiz' => $quizid), null, 'question')) {
        $linkervars = $DB->get_records_list('qtype_programmedresp_var', 'question', array_keys($qinquiz));
    }
}
$outputmanager = new programmedresp_output_ajax($mform, $linkervars);
switch ($action) {

    // Question text vars
    case 'displayvars' :
        $displayfunctionbutton = optional_param('displayfunctionbutton', true, PARAM_INT);
        $outputmanager->display_vars(false, false, $displayfunctionbutton);
        break;

    // Functions <select>
    case 'displayfunctionslist' :
        $categoryid = optional_param('categoryid', false, PARAM_INT);
        $outputmanager->display_functionslist($categoryid);
        break;

    // Function arguments
    case 'displayargs' :
        $functionid = optional_param('function', false, PARAM_INT);
        $outputmanager->display_args($functionid);
        break;

    case 'addconcatvar' :
        $concatnum = optional_param('concatnum', false, PARAM_INT);
        $vars = optional_param('vars', false, PARAM_ALPHANUM);
        $outputmanager->add_concat_var("concatvar_" . $concatnum, $vars, false, false);
        break;
}
