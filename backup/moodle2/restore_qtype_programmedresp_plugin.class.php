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
 * @package    moodlecore
 * @subpackage backup-moodle2
 * @copyright  2016 onwards Gerard Cuello {gerard.urv@gmail.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * Restore plugin class that provides the necessary information
 * needed to restore one programmedresp qtype plugin
 *
 * @copyright  2016 onwards Gerard Cuello {gerard.urv@gmail.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_programmedresp_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level
     */
    protected function define_question_plugin_structure() {

        $paths = array();

        // Add own qtype stuff.
        $elename = 'var';
        $elepath = $this->get_pathfor('/vars/var');
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = 'concatvar';
        $elepath = $this->get_pathfor('/concatvars/concatvar');
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = 'arg';
        $elepath = $this->get_pathfor('/args/arg');
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = 'resp';
        $elepath = $this->get_pathfor('/resps/resp');
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = 'function';
        $elepath = $this->get_pathfor('/function');
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = 'programmedresp';
        $elepath = $this->get_pathfor('/programmedresp');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths.
    }

    /**
     * Process the qtype/programmedresp_var element
     */
    public function process_var($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ?
            true : false;

        // Si la variable ha estat insertada previament (per la qtype-linkerdesc se suposa)
        // no l'hem d'insertar.
        if ($newvarid = $this->get_mappingid('var', $oldid)){
            return true;
        }
        // If the question has been created by restore, we need to create its
        // vars too.
        if ($questioncreated) {
            // Adjust some columns.
            $data->question = $newquestionid;
            // Insert record.
            // TODO: Ensure there aren't vars with the same name
            $newitemid = $DB->insert_record('qtype_programmedresp_var', $data);

            // Mapping the old var id with the new inserted one.
            // It will be used later by programmedresp_arg item to get the correct var id.
            $this->set_mapping('var', $oldid, $newitemid);
        }

    }

    public function process_concatvar($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ?
            true : false;

        if ($newconcatid = $this->get_mappingid('concatvar', $oldid)){
            return true;
        }
        // If the question has been created by restore, we need to create its
        // concatenated vars too.
        if ($questioncreated) {
            // Adjust some columns.
            $data->question = $newquestionid;
            // Insert record.
            $newitemid = $DB->insert_record('qtype_programmedresp_conc', $data);

            $this->set_mapping('concatvar', $oldid, $newitemid);
        }
    }


    /**
     * TODO: Restore function code once the backup will be done!
     * @param $data
     */
    public function process_function($data) {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/question/type/programmedresp/lib.php');
        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $questioncreated = $this->get_mappingid('question', $oldquestionid) ?
            true : false;

        $function = $DB->get_record('qtype_programmedresp_f',
            array('name' => $data->name));
        // If the question has been created by restore, we need to create its
        // concatenated vars too.
        if($questioncreated) {
            if (!$function) {
                $data->programmedrespfcatid = programmedresp_check_base_functions_category();
                $newitemid = $DB->insert_record('qtype_programmedresp_f', $data);
                // once else if it will be move below
            } else if ($function){
                $newitemid = $function->id;
            }

            $this->set_mapping('function', $oldid, $newitemid);
        }

    }

    public function process_programmedresp($data) {
        global $DB;

        $data = (object)$data;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ?
            true : false;

        // If the question has been created by restore, we need to create its
        // concatenated vars too.
        $oldfunctionid = $data->programmedrespfid;
        $functioncreated = $this->get_mappingid('function', $oldfunctionid);
        if ($questioncreated && $functioncreated) {
            $data->question = $newquestionid;
            $data->programmedrespfid = $functioncreated;
            $DB->insert_record('qtype_programmedresp', $data);
        }


    }

    /**
     * TODO: Ensure var and concat var have been created.
     * @param $data
     */
    public function process_arg($data) {
        global $DB;
        $data = (object)$data;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ?
            true : false;

        // If the question has been created by restore, we need to create its
        // concatenated vars too.
        if ($questioncreated) {
            $data->question = $newquestionid;
            // var type
            if ($data->type == 1) {
                $oldvarid = $data->value;
                $data->value = $this->get_mappingid('var', $oldvarid);
            // concat var type
            } else if ($data->type == 3) {
                $oldvarid = $data->value;
                $data->value = $this->get_mappingid('concatvar', $oldvarid);
            }
            $DB->insert_record('qtype_programmedresp_arg', $data);
        }
    }

    public function process_resp($data) {
        global $DB;
        $data = (object)$data;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ?
            true : false;

        // If the question has been created by restore, we need to create its
        // concatenated vars too.
        if ($questioncreated) {
            $data->question = $newquestionid;
            $DB->insert_record('qtype_programmedresp_resp', $data);
        }
    }

}
