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
            $dbman->add_field($table, $customfield);
        }

        // Insert new config parameters into global settings
        if (!$DB->record_exists_sql('SELECT * FROM {config_plugins} WHERE plugin = ? AND name = ?',
        ['local_eventocoursecreation', 'starttimecourse'])) {
            $DB->insert_record('config_plugins', ['plugin' => 'local_eventocoursecreation', 'name' => 'starttimecourse', 'value' => 946681200]);
        }

        if (!$DB->record_exists_sql('SELECT * FROM {config_plugins} WHERE plugin = ? AND name = ?',
        ['local_eventocoursecreation', 'setcustomcoursestarttime'])) {
            $DB->insert_record('config_plugins', ['plugin' => 'local_eventocoursecreation', 'name' => 'setcustomcoursestarttime', 'value' => 0]);
        }

        // Eventocoursecreation savepoint reached.
        upgrade_plugin_savepoint(true, 2023121800, 'local', 'eventocoursecreation');
    }

    if ($oldversion < 2026090100) {

        // Define table eventocoursecreation_page to be created.
        $table = new xmldb_table('eventocoursecreation_page');

        // Adding fields to table eventocoursecreation_page.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('anlassnummer', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('idmb', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('idstatus', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('mbversionscaled', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('mbgueltigab', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, null, null, null);
        $table->add_field('pagename', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('errorcount', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lasterror', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timechecked', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table eventocoursecreation_page.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN_UNIQUE, ['courseid'], 'course', ['id']);

        // Adding indexes to table eventocoursecreation_page.
        $table->add_index('cmid', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
        $table->add_index('anlassnummer', XMLDB_INDEX_NOTUNIQUE, ['anlassnummer']);
        $table->add_index('timechecked', XMLDB_INDEX_NOTUNIQUE, ['timechecked']);

        // Conditionally launch create table for eventocoursecreation_page.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Eventocoursecreation savepoint reached.
        upgrade_plugin_savepoint(true, 2026090100, 'local', 'eventocoursecreation');
    }

    return true;
}
