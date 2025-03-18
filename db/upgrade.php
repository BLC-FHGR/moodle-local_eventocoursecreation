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
    global $DB, $CFG;
    $dbman = $DB->get_manager();

    if ($oldversion < 2023121808) {
        // Define field subcatorganization to be added to eventocoursecreation
        $table = new xmldb_table('eventocoursecreation');
        $field = new xmldb_field('subcatorganization', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1', 'numberofsections');

        // Add field if it doesn't exist
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2023121808, 'local', 'eventocoursecreation');
    }

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

    if ($oldversion < 2023121812) {
        // Define fields to add to eventocoursecreation table.
        $table = new xmldb_table('eventocoursecreation');
        
        // Check if table exists before proceeding
        if ($dbman->table_exists($table)) {
            // Debugging output
            mtrace("Adding API optimization fields to eventocoursecreation table");
            
            // Add enable_api_optimization field - without depending on previous field
            $field = new xmldb_field('enable_api_optimization', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);
            if (!$dbman->field_exists($table, $field)) {
                mtrace("Adding field: enable_api_optimization");
                $dbman->add_field($table, $field);
            } else {
                mtrace("Field already exists: enable_api_optimization");
            }
            
            // Add fetching_mode field - without depending on previous field
            $field = new xmldb_field('fetching_mode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'smart');
            if (!$dbman->field_exists($table, $field)) {
                mtrace("Adding field: fetching_mode");
                $dbman->add_field($table, $field);
            } else {
                mtrace("Field already exists: fetching_mode");
            }
            
            // Add custom_batch_size field - without depending on previous field
            $field = new xmldb_field('custom_batch_size', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0);
            if (!$dbman->field_exists($table, $field)) {
                mtrace("Adding field: custom_batch_size");
                $dbman->add_field($table, $field);
            } else {
                mtrace("Field already exists: custom_batch_size");
            }
            
            // Add override_global_fetching field - without depending on previous field
            $field = new xmldb_field('override_global_fetching', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, 0);
            if (!$dbman->field_exists($table, $field)) {
                mtrace("Adding field: override_global_fetching");
                $dbman->add_field($table, $field);
            } else {
                mtrace("Field already exists: override_global_fetching");
            }
        } else {
            mtrace("Error: eventocoursecreation table doesn't exist!");
        }
        
        // Set plugin configuration defaults if not already set.
        mtrace("Setting default configuration values");
        
        if (!isset($CFG->local_eventocoursecreation_fetching_mode)) {
            set_config('fetching_mode', 'smart', 'local_eventocoursecreation');
        }
        
        if (!isset($CFG->local_eventocoursecreation_batch_size)) {
            set_config('batch_size', '200', 'local_eventocoursecreation');
        }
        
        if (!isset($CFG->local_eventocoursecreation_min_batch_size)) {
            set_config('min_batch_size', '10', 'local_eventocoursecreation');
        }
        
        if (!isset($CFG->local_eventocoursecreation_max_batch_size)) {
            set_config('max_batch_size', '1000', 'local_eventocoursecreation');
        }
        
        if (!isset($CFG->local_eventocoursecreation_adaptive_batch_sizing)) {
            set_config('adaptive_batch_sizing', '1', 'local_eventocoursecreation');
        }
        
        if (!isset($CFG->local_eventocoursecreation_date_chunk_fallback)) {
            set_config('date_chunk_fallback', '1', 'local_eventocoursecreation');
        }
        
        if (!isset($CFG->local_eventocoursecreation_date_chunk_days)) {
            set_config('date_chunk_days', '90', 'local_eventocoursecreation');
        }
        
        if (!isset($CFG->local_eventocoursecreation_max_api_retries)) {
            set_config('max_api_retries', '3', 'local_eventocoursecreation');
        }
        
        if (!isset($CFG->local_eventocoursecreation_cache_ttl)) {
            set_config('cache_ttl', '3600', 'local_eventocoursecreation');
        }
        
        // Upgrade savepoint.
        upgrade_plugin_savepoint(true, 2023121812, 'local', 'eventocoursecreation');
    }

    return true;
}
