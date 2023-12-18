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
 * Upgrade script for the eventocoursecreation module.
 *
 * @package    local_eventocoursecreation
 * @copyright  2023 FHGR Julien Rädler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Eventocoursecreation module upgrade function.
 * @param string $oldversion the version we are upgrading from.
 */
function xmldb_local_eventocoursecreation_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2023121800) {

        // Define field completionminattempts to be added to eventocoursecreation.
        $table = new xmldb_table('eventocoursecreation');
        $timefield = new xmldb_field('starttimecourse', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '946681200');
        $customfield = new xmldb_field('setcustomcoursestarttime', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Conditionally launch add field completionminattempts.
        if (!$dbman->field_exists($table, $timefield)) {
            $dbman->add_field($table, $timefield);
        }

        if (!$dbman->field_exists($table, $customfield)) {
            $dbman->add_field($table, $timefield);
        }

        // Insert new config parameters into global settings
        if (!$DB->record_exists('config_plugins', ['plugin' => 'local_eventocoursecreation', 'name' => 'starttimecourse'])) {
            $DB->insert_record('config_plugins', ['plugin' => 'local_eventocoursecreation', 'name' => 'starttimecourse', 'value' => 946681200]);
        }

        if (!$DB->record_exists('config_plugins', ['plugin' => 'local_eventocoursecreation', 'name' => 'setcustomcoursestarttime'])) {
            $DB->insert_record('config_plugins', ['plugin' => 'local_eventocoursecreation', 'name' => 'setcustomcoursestarttime', 'value' => 0]);
        }

        // Eventocoursecreation savepoint reached.
        upgrade_plugin_savepoint(true, 2023121800, 'eventocoursecreation', 'local');
    }

    return true;
}
