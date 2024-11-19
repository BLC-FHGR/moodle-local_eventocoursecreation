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
 * Evento course creation plugin
 *
 * @package    local_eventocoursecreation
 * @copyright  2018 HTW Chur Roger Barras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * This function adds a Course Creation setting node to a categorie if the categorie idnumber is set.
 *
 * @param navigation_node $parentnode The navigation node to extend
 * @param context_coursecat $context The context of the course category
 */
function local_eventocoursecreation_extend_navigation_category_settings(navigation_node $parentnode, context_coursecat $context) {
    global $DB;

    // Check if it is enabled.
    if (!get_config('local_eventocoursecreation', 'enableplugin')) {
        return false;
    }

    // Get the idnumber of the course category that is curently shown
    $cc = $DB->get_field('course_categories', 'idnumber', array('id' => $context->instanceid));

    // Check if the idnumber fullfils all conditions that are set by course creation to even aply evento settings
    // idnumber is not empty
    // idnumber starts with mod
    // idnumber doesn't contain .FS or .HS
    if (
        empty($cc) || 
        substr(strtoupper($cc), 0, 3 ) ==! strtoupper('mod') ||
        strpos(strtoupper($cc), strtoupper('.FS')) ==! false ||
        strpos(strtoupper($cc), strtoupper('.HS')) ==! false
        ) {
            return false;
        }

    // Add the course creation link.
    $pluginname = get_string('pluginname', 'local_eventocoursecreation');
    $url = new moodle_url( '/local/eventocoursecreation/setting_form.php', array (
                        'contextid' => $context->id
                        ) );
    $node = navigation_node::create(
        $pluginname,
        $url,
        navigation_node::TYPE_SETTING,
        'local_eventocoursecreation',
        'local_eventocoursecreation',
        new pix_icon('t/preferences', $pluginname, 'moodle')
    );
    if (isset($node)) {
        $parentnode->add_node($node);
    }

}