<?php

/**
 * Manages the functions and categories of the system
 *
 * @copyright 2010 David Monllaó <david.monllao@urv.cat>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package qtype_programmedresp
 */
require_once('../../../config.php');

global $CFG, $PAGE, $OUTPUT;

require_once($CFG->dirroot . '/question/type/programmedresp/forms/programmedresp_addcategory_form.php');
require_once($CFG->dirroot . '/question/type/programmedresp/forms/programmedresp_addfunctions_form.php');
require_once($CFG->dirroot . '/question/type/programmedresp/functions_tokenizer.class.php');
require_once($CFG->dirroot . '/question/type/programmedresp/lib.php');

require_login();
require_capability('moodle/question:config', context_system::instance());
$PAGE->requires->js('/question/type/programmedresp/script.js');
$action = required_param('action', PARAM_ALPHA);
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url($CFG->wwwroot . '/question/type/programmedresp/manage.php', array('action' => $action)));

$PAGE->set_title(get_string($action, 'qtype_programmedresp'));
$PAGE->set_heading(get_string($action, 'qtype_programmedresp'));
$PAGE->set_focuscontrol('');
$PAGE->set_cacheable(true);
echo $OUTPUT->header();


switch ($action) {

    case 'addcategory':

        $catoptions[0] = get_string('root', 'qtype_programmedresp');
        $categories = $DB->get_records('qtype_programmedresp_fcat', array(), 'id ASC', 'id, parent, name');
        if ($categories) {
            foreach ($categories as $key => $cat) {
                if (empty($catoptions[$cat->id])) {
                    $catoptions[$cat->id] = $cat->name;
                    unset($categories[$key]);
                    programmedresp_add_child_categories($cat->id, $catoptions, $categories);
                }
            }
        }

        $form = new programmedresp_addcategory_form($CFG->wwwroot . '/question/type/programmedresp/manage.php',
            array('categories' => $catoptions));

        // Insert category
        if ($data = $form->get_data()) {

            $catdata->parent = (int) $data->parent;

            $catdata->name = $data->name;
            if (!$catdata->id = $DB->insert_record('qtype_programmedresp_fcat', $catdata)) {
                print_error('errordb', 'qtype_programmedresp');
            }

            $params = array($catdata->id, $catdata->name, "id_functioncategory", $data->parent);
            $PAGE->requires->js_init_call('exec_js_manage', $params);

            // Display form
        } else {
            $form->display();
        }
        break;


    case 'addfunctions':

        $fcatid = optional_param('fcatid', false, PARAM_INT);

        if (!$fcatid) {
            echo '<br/><br/>';
            notify(get_string('firstselectcategory', 'qtype_programmedresp'));
            die();
        }

        $form = new programmedresp_addfunctions_form($CFG->wwwroot . '/question/type/programmedresp/manage.php', array('fcatid' => $fcatid));

        if ($form->is_cancelled()) {
            echo '<script type="text/javascript">window.close();</script>';
        } else if ($data = $form->get_data()) {

            $data->functionstextarea = stripslashes($data->functionstextarea);

            $tokenizer = new functions_tokenizer();
            if (!$tokenizer->set_code($data->functionstextarea)) {
                notify(get_string('errorsyntax', 'qtype_programmedresp'), 'notifyproblem');
                $form->set_data(array('functionstextarea' => $data->functionstextarea));
            } else {
                $functions = $tokenizer->get_functions();
            }

            // If there aren't valid functions display the form again
            if (empty($functions)) {
                notify(get_string('errornovalidfunctions', 'qtype_programmedresp'), 'notifyproblem');
                $form->set_data(array('functionstextarea' => $data->functionstextarea));

                // Add functions data
            } else {
                foreach ($functions as $function) {
                    if ($DB->get_record('qtype_programmedresp_f', array('name' => $function->name)) || programmedresp_get_function_code($function->name)) {
                        notify('<strong>'.$function->name . '</strong>: ' . get_string('errorfunctionalreadycreated', 'qtype_programmedresp'), 'notifyproblem');
                        continue;
                    }
                    $fdata = new stdClass();
                    $fdata->programmedrespfcatid = $fcatid;
                    $fdata->name = $function->name;
                    $fdata->description = addslashes($function->description);
                    $fdata->nreturns = $function->nreturns;
                    $fdata->params = addslashes(programmedresp_serialize($function->params));
                    $fdata->results = addslashes(programmedresp_serialize($function->results));
                    $fdata->timeadded = time();

                    if (!$fdata->id = $DB->insert_record('qtype_programmedresp_f', $fdata)) {
                        print_error('errordb', 'qtype_programmedresp');
                    }

                    notify(get_string('functionadded', 'qtype_programmedresp', $function->name), 'notifysuccess', 'center');
                    programmedresp_add_repository_function($function->functioncode);

                    // Array to add to the parent select functions form element
                    $fdatas[] = clone $fdata;
                }
            }

            // Display errors found
            $errors = $tokenizer->get_errors();
            if ($errors) {
                foreach ($errors as $error) {
                    notify($error, 'error');
                }
            }

            // Add the functions created to the form
            if (!empty($fdatas)) {
                foreach ($fdatas as $f) {
                    $PAGE->requires->js_init_call('exec_js_funct_manage', array($f->id, $f->name, "id_programmedrespfid"));
                }
            }
        }

        // Display the form anyway
        $form->display();

        echo '<a href="#" onclick="window.close();" style="text-align: center;">' . get_string("closewindow", "qtype_programmedresp") . '</a>';
        break;

    default:
        break;
}

echo $OUTPUT->footer();
