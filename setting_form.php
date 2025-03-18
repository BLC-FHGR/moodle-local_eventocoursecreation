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

require('../../config.php');
require_once($CFG->libdir .'/weblib.php');
require_once($CFG->dirroot . '/local/eventocoursecreation/lib.php');
//require_once($CFG->libdir.'/coursecatlib.php'); deprecated since Moodle 3.10

require_login(true);

$contextid = optional_param( 'contextid', 0, PARAM_INT );
$context = context::instance_by_id($contextid);
$catid = $context->instanceid;

$manageurl = new moodle_url('/course/index.php');
$manageurl->param('categoryid', $catid);

// Check if the context is CONTEXT_COURSECAT.
if ($context->contextlevel !== CONTEXT_COURSECAT) {
    debugging('Wrong contextlevel: level ' . $context->contextlevel . ' instead of ' . CONTEXT_COURSECAT, DEBUG_DEVELOPER);
    redirect ($manageurl);
}

if ($catid) {
    $coursecat = core_course_category::get($catid, MUST_EXIST, true);
    $category = $coursecat->get_db_record();
    $url = new moodle_url('/local/eventocoursecreation/setting_form.php', array('contextid' => $contextid));
    $url->param('id', $catid);
    $strtitle = get_string('editcreationsettings', 'local_eventocoursecreation');
    $itemid = 0; // Initialise itemid, as all files in category description has item id 0.
    $title = $strtitle;
    $fullname = $coursecat->get_formatted_name();

} else {
    debugging('No category is set!', DEBUG_DEVELOPER);
    redirect ($manageurl);
}

// Check if this is a semester/year subcategory
if (!empty($category->idnumber) && local_eventocoursecreation_is_semester_subcategory($category->idnumber)) {
    // Get the parent Veranstalter ID
    $parentIdNumber = '';
    
    if (preg_match('/(.+)_(?:FS|HS)\d{2}$/', $category->idnumber, $matches)) {
        $parentIdNumber = $matches[1];
    } else if (preg_match('/(.+)_20\d{2}$/', $category->idnumber, $matches)) {
        $parentIdNumber = $matches[1];
    }
    
    // Try to find the parent category for a better message
    $parentCategory = null;
    if (!empty($parentIdNumber)) {
        $parentCategory = $DB->get_record('course_categories', ['idnumber' => $parentIdNumber]);
    }
    
    $message = get_string('evento_settings_unavailable_for_subcategory', 'local_eventocoursecreation');
    if ($parentCategory) {
        $parentUrl = new moodle_url('/local/eventocoursecreation/setting_form.php', 
            ['contextid' => context_coursecat::instance($parentCategory->id)->id]);
        $message .= ' ' . get_string('evento_settings_edit_parent', 'local_eventocoursecreation', 
            ['category' => format_string($parentCategory->name), 'url' => $parentUrl->out()]);
    }
    
    redirect($manageurl, $message, null, \core\output\notification::NOTIFY_INFO);
}

require_capability('moodle/category:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');

$PAGE->set_title($title);
$PAGE->set_heading($fullname);

if (isset($category)) {
    $setting = local_eventocoursecreation_setting::get($category->id);
} else {
    debugging('No category is set!', DEBUG_DEVELOPER);
    redirect ($manageurl);
}

// Set the initial data.
$data = array (
    'categoryid' => $catid,
    'contextid' => $contextid,
    'idnumber' => $category->idnumber
);

$mform = new local_eventocoursecreation_setting_form(null, array($data));

$settingdata = $setting->get_db_record();

$mform->set_data($settingdata);

if ($mform->is_cancelled()) {
    redirect ( $manageurl );
}

if ($data = $mform->get_data()) {

    if (isset($coursecat) && isset($category)) {
        $setting->update($data);
        // Update category idnumber.
        if ($category->idnumber != $data->idnumber) {
            $category->idnumber = $data->idnumber;
            $coursecat->update($data);
        }
        redirect ($manageurl);
    }
} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('editcreationsettings', 'local_eventocoursecreation'));
    $mform->display();
    echo $OUTPUT->footer();
    die();
}
