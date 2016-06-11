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

    if ($oldversion < 2016060700) {

        // Define field origin to be added to qtype_programmedresp_arg.
        $table = new xmldb_table('qtype_programmedresp_arg');
        $field = new xmldb_field('origin', XMLDB_TYPE_CHAR, '8', null, XMLDB_NOTNULL, null, null, 'value');

        // Conditionally launch add field origin.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Programmedresp savepoint reached.
        upgrade_plugin_savepoint(true, 2016060700, 'qtype', 'programmedresp');
    }


    return true;
}
