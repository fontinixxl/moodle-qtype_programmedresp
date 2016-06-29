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
 * Programmed responses question type upgrade code.
 *
 * @package    qtype
 * @subpackage programmedresp
 * @copyright  THEYEAR Gerard Cuello (<gerard.urv@gmail.com>)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade code for the programmedresp question type.
 * @param int $oldversion the version we are upgrading from.
 */
function xmldb_qtype_programmedresp_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Només per a TEST! Ningu tindrà aquesta versió instalada per tant això es podrà eliminar.
    // La intenció es fer-ho servir per que no es perdin dades de MOODLE-DEIM.
    if ($oldversion < 2016062000) {

        // Define field origin to be added to qtype_programmedresp_arg.
        $table = new xmldb_table('qtype_programmedresp_arg');

        // Eliminem index per poder canviar el camp type
        $index = new xmldb_index('question_type', XMLDB_INDEX_NOTUNIQUE, array('question', 'type'));
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // 'type' ara pot ser NULL (quan editem un pregunta desde question bank)
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'origin');
        $dbman->change_field_type($table, $field);

        // Afegim nom camp origin
        $field = new xmldb_field('origin', XMLDB_TYPE_CHAR, '8', null, XMLDB_NOTNULL, null, null, 'value');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Necessitem migrar les dades que hi ha actualment a DB cap al nou model.
            $args = $DB->get_records('qtype_programmedresp_arg');
            foreach ($args as $argid => $arg) {
                // Si el tipus de variable es diferent a 2 significa que es local (var, fixed o concat)
                // actualitzem 'origin' a 'local'
                if ($arg->type != '2') {
                    $arg->origin = 'local';
                } else {
                    // Per defecte i contemplant la possibilitat que no existeixi variable assignada per cap
                    // questionari, buidem els camps.
                    unset($arg->type, $arg->value);
                    // Nomes ens interessa un unic valor... (IGNORE_MULTIPLE)
                    if ($var = $DB->get_record('qtype_programmedresp_v_arg', array('programmedrespargid' => $arg->id),
                        'type, instanceid', IGNORE_MULTIPLE)) {
                        $arg->type = ($var->type === 'var') ? 1 : 3;
                        $arg->value = $var->instanceid;
                    }
                    $arg->origin = 'linker';
                }

                $DB->update_record('qtype_programmedresp_arg', $arg);

                // Once all data has been migrated we can delete the old qtype_programmedresp_v_arg
                $dbman->drop_table(new xmldb_table('qtype_programmedresp_v_arg'));
            }

        }


        // Programmedresp savepoint reached.
        upgrade_plugin_savepoint(true, 2016062000, 'qtype', 'programmedresp');
    }


    return true;
}
