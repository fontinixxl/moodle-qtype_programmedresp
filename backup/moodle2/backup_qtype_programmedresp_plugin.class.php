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

// TODO: Add backup of function categories (qtype_programmedresp_fcat)

/**
 * Provides the information to backup programmedresp questions
 *
 * @copyright  2016 onwards Gerard Cuello {gerard.urv@gmail.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_programmedresp_plugin extends backup_qtype_plugin
{

    /**
     * Returns the qtype information to attach to question element
     */
    protected function define_question_plugin_structure()
    {

        // Define the virtual plugin element with the condition to fulfill.
        // Note: we use $this->pluginname so for extended plugins this will work

        $plugin = $this->get_plugin_element(null, '../../qtype', $this->pluginname);

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // Now create the qtype own structures.
        $vars = new backup_nested_element('vars');
        $var = new backup_nested_element('var', array('id'), array(
            'varname', 'nvalues', 'maximum', 'minimum', 'valueincrement'
        ));
        $concatvars = new backup_nested_element('concatvars');
        $concatvar = new backup_nested_element('concatvar', array('id'), array(
            'name', 'readablename', 'vars'
        ));
        // Adding additional -code- element to save
        // the function code (stored in a file on dataroot)
        $function = new backup_nested_element('function', array('id'), array(
            'programmedrespfcatid', 'name', 'description', 'nreturns', 'params', 'results'
        ));
        $programmedresp = new backup_nested_element('programmedresp', array('id'), array(
            'programmedrespfid', 'tolerancetype', 'tolerance'
        ));
        // Each question can have some function arguments.
        $args = new backup_nested_element('args');
        $arg = new backup_nested_element('arg', array('id'), array(
            'argkey', 'type', 'value'
        ));
        // Each question can have some function arguments.
        $resps = new backup_nested_element('resps');
        $resp = new backup_nested_element('resp', array('id'), array(
            'returnkey', 'label'
        ));

        // Now the own qtype tree.
        $pluginwrapper->add_child($vars);
        $vars->add_child($var);

        $pluginwrapper->add_child($concatvars);
        $concatvars->add_child($concatvar);

        $pluginwrapper->add_child($args);
        $args->add_child($arg);
        $pluginwrapper->add_child($resps);
        $resps->add_child($resp);

        // Dependences between them
        $pluginwrapper->add_child($function);
        $pluginwrapper->add_child($programmedresp);

        // Set source to populate the data.
        $var->set_source_table('qtype_programmedresp_var',
            array('question' => backup::VAR_PARENTID));

        $concatvar->set_source_sql("
            SELECT conc.*
              FROM {qtype_programmedresp_arg} arg
              JOIN {qtype_programmedresp_conc} conc ON conc.id = arg.value
             WHERE conc.question = ?
                   AND arg.type = '3'",
            array(backup::VAR_PARENTID));

        $arg->set_source_table('qtype_programmedresp_arg',
            array('question' => backup::VAR_PARENTID));

        $resp->set_source_table('qtype_programmedresp_resp',
            array('question' => backup::VAR_PARENTID));

        // TODO: Backup function CODE.
        $function->set_source_sql('
            SELECT f.*
              FROM {qtype_programmedresp_f} f
              JOIN {qtype_programmedresp} p ON f.id = p.programmedrespfid
             WHERE p.question = ?',
            array(backup::VAR_PARENTID));

        $programmedresp->set_source_table('qtype_programmedresp',
            array('question' => backup::VAR_PARENTID));

        // Don't need to annotate ids nor files.
        return $plugin;
    }

}
