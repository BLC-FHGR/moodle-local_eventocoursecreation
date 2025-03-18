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
 * @copyright  2025 HTW FHGR Julien Rädler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Determines if a category is a semester/year subcategory
 * 
 * @param string $idNumber The category idnumber
 * @return bool True if it's a subcategory
 */
function local_eventocoursecreation_is_semester_subcategory(string $idNumber): bool
{
    // Check for typical semester pattern at the end (FS## or HS##)
    if (preg_match('/_(?:FS|HS)\d{2}$/', $idNumber)) {
        return true;
    }
    
    // Check for year pattern at the end (_YYYY)
    if (preg_match('/_20\d{2}$/', $idNumber)) {
        return true;
    }
    
    return false;
}

/**
 * This function adds a Course Creation setting node to a category if the category idnumber is set
 * and it's not a semester/year subcategory.
 *
 * @param navigation_node $parentnode The navigation node to extend
 * @param context_coursecat $context The context of the course category
 */
function local_eventocoursecreation_extend_navigation_category_settings(
    navigation_node $parentnode, 
    context_coursecat $context
) {
    global $DB;
    
    // Get the category information
    $category = $DB->get_record('course_categories', ['id' => $context->instanceid]);
    
    // Skip if it's a semester/year subcategory
    if (!empty($category->idnumber) && local_eventocoursecreation_is_semester_subcategory($category->idnumber)) {
        return;
    }

    // Add the navigation node
    $pluginname = get_string('pluginname', 'local_eventocoursecreation');
    $url = new moodle_url('/local/eventocoursecreation/setting_form.php', [
        'contextid' => $context->id
    ]);
    
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