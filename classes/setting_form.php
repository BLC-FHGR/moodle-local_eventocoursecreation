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
 * Custom settings interface for evento course creation
 *
 * @package   local_eventocoursecreation
 * @copyright 2018 HTW Chur Roger Barras
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined( 'MOODLE_INTERNAL' ) || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Evento course Creation setting form
 *
 * @copyright  2018 HTW Chur Roger Barras
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_eventocoursecreation_setting_form extends moodleform {

    // Define the form.
    public function definition() {
        global $OUTPUT, $DB, $PAGE;
        $mform = $this->_form;

        list($data) = $this->_customdata;
        $categoryid = $data['categoryid'];
        $contextid = $data['contextid'];
        $idnumber = $data['idnumber'];

        // Default values.
        $config = get_config('local_eventocoursecreation');

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->setExpanded('general');

        // Enable Disable.
        $choices = array();
        $choices['0'] = get_string('disabled', 'local_eventocoursecreation');
        $choices['1'] = get_string('enabled', 'local_eventocoursecreation');
        $mform->addElement('select', 'enablecatcoursecreation', get_string('enablecatcoursecreation', 'local_eventocoursecreation'), $choices);
        $mform->addHelpButton('enablecatcoursecreation', 'enablecatcoursecreation', 'local_eventocoursecreation');
        $mform->setDefault('enablecatcoursecreation', 1);

        // Coursofstudies = category idnumber.
        $mform->addElement('text', 'idnumber', get_string('idnumber', 'local_eventocoursecreation'), array('size' => '15'));
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->setDefault('idnumber', $idnumber);
        $mform->addHelpButton('idnumber', 'idnumber', 'local_eventocoursecreation');

        // Default course settings.
        $mform->addElement('header', 'defaultcourssettings', get_string('defaultcourssettings', 'local_eventocoursecreation'));
        $mform->setExpanded('defaultcourssettings');

        // Course visability.
        $choices = array();
        $choices['0'] = get_string('hide');
        $choices['1'] = get_string('show');
        $mform->addElement('select', 'coursevisibility', get_string('coursevisibility'), $choices);
        $mform->addHelpButton('coursevisibility', 'coursevisibility');
        $mform->setDefault('coursevisibility', $config->coursevisibility);

        // Newsitems.
        $options = range(0, 10);
        $mform->addElement('select', 'newsitemsnumber', get_string('newsitemsnumber'), $options);
        $mform->addHelpButton('newsitemsnumber', 'newsitemsnumber');
        $mform->setDefault('newsitemsnumber', $config->newsitemsnumber);

        // Number of sections.
        $options = range(0, 20);
        $mform->addElement('select', 'numberofsections', get_string('numberofsections', 'local_eventocoursecreation'), $options);
        $mform->addHelpButton('numberofsections', 'numberofsections', 'local_eventocoursecreation');
        $mform->setDefault('numberofsections', $config->numberofsections);

        // Template course selection.
        $options = array(
            'requiredcapabilities' => array('moodle/category:manage'),
            'multiple' => false
        );
        $mform->addElement('course', 'templatecourse', get_string('templatecourse', 'local_eventocoursecreation'), $options);
        $mform->addHelpButton('templatecourse', 'templatecourse', 'local_eventocoursecreation');

        // Enable/Disable Template.
        $choices = array();
        $choices['0'] = get_string('no', 'local_eventocoursecreation');
        $choices['1'] = get_string('yes', 'local_eventocoursecreation');
        $mform->addElement('select', 'enablecoursetemplate', get_string('enablecoursetemplate', 'local_eventocoursecreation'), $choices);
        $mform->addHelpButton('enablecoursetemplate', 'enablecoursetemplate', 'local_eventocoursecreation');
        $mform->setDefault('enablecoursetemplate', 0);
        
        // Add subcategory organization setting
        $subcatorganization = array(
            EVENTOCOURSECREATION_SUBCAT_NONE => get_string('subcatorg_none', 'local_eventocoursecreation'),
            EVENTOCOURSECREATION_SUBCAT_SEMESTER => get_string('subcatorg_semester', 'local_eventocoursecreation'),
            EVENTOCOURSECREATION_SUBCAT_YEAR => get_string('subcatorg_year', 'local_eventocoursecreation')
        );

        $mform->addElement('select', 'subcatorganization', 
            get_string('subcatorganization', 'local_eventocoursecreation'), 
            $subcatorganization);
        $mform->addHelpButton('subcatorganization', 'subcatorganization', 'local_eventocoursecreation');
        $mform->setDefault('subcatorganization', $config->defaultsubcatorganization);

        // Days.
        $days = array_combine(range(1, 31), range(1, 31));

        // Months.
        $months = array();
        $months['1'] = get_string('january', 'local_eventocoursecreation');
        $months['2'] = get_string('february', 'local_eventocoursecreation');
        $months['3'] = get_string('march', 'local_eventocoursecreation');
        $months['4'] = get_string('april', 'local_eventocoursecreation');
        $months['5'] = get_string('may', 'local_eventocoursecreation');
        $months['6'] = get_string('june', 'local_eventocoursecreation');
        $months['7'] = get_string('july', 'local_eventocoursecreation');
        $months['8'] = get_string('august', 'local_eventocoursecreation');
        $months['9'] = get_string('september', 'local_eventocoursecreation');
        $months['10'] = get_string('october', 'local_eventocoursecreation');
        $months['11'] = get_string('november', 'local_eventocoursecreation');
        $months['12'] = get_string('december', 'local_eventocoursecreation');

        // Custom course creation parameters
        $mform->addElement('header', 'customcoursesettings', get_string('customcoursesettings', 'local_eventocoursecreation'));
        $mform->setExpanded('customcoursesettings');

        $mform->addElement('date_time_selector', 'starttimecourse', get_string('coursestart', 'local_eventocoursecreation'));
        $mform->addHelpButton('starttimecourse', 'coursestart', 'local_eventocoursecreation');
        $mform->setDefault('starttimecourse', $config->starttimecourse);
        $mform->addElement('advcheckbox', 'setcustomcoursestarttime', get_string('setcustomcoursestart', 'local_eventocoursecreation'),
                            '', null, array(0, 1));
        $mform->setDefault('setcustomcoursestarttime', $config->setcustomcoursestarttime);
        $mform->addHelpButton('setcustomcoursestarttime', 'setcustomcoursestart', 'local_eventocoursecreation');

        // Spring Term.
        $mform->addElement('header', 'startspringterm', get_string('startspringterm', 'local_eventocoursecreation'));
        $mform->setExpanded('startspringterm');

        $mform->addElement('select', 'starttimespringtermday', get_string('springstartday', 'local_eventocoursecreation'), $days);
        $mform->addHelpButton('starttimespringtermday', 'springstartday', 'local_eventocoursecreation');
        $mform->setDefault('starttimespringtermday', $config->starttimespringtermday);
        $mform->addElement('select', 'starttimespringtermmonth', get_string('springstartmonth', 'local_eventocoursecreation'), $months);
        $mform->addHelpButton('starttimespringtermmonth', 'springstartmonth', 'local_eventocoursecreation');
        $mform->setDefault('starttimespringtermmonth', $config->starttimespringtermmonth);
        $mform->addElement('advcheckbox', 'execonlyonstarttimespringterm', get_string('execonlyonstarttimespringterm', 'local_eventocoursecreation'),
                            '', null, array(0, 1));
        $mform->setDefault('execonlyonstarttimespringterm', $config->execonlyonstarttimespringterm);
        $mform->addHelpButton('execonlyonstarttimespringterm', 'execonlyonstarttimespringterm', 'local_eventocoursecreation');

        // Autumn Term.
        $mform->addElement('header', 'startautumnterm', get_string('startautumnterm', 'local_eventocoursecreation'));
        $mform->setExpanded('startautumnterm');

        $mform->addElement('select', 'starttimeautumntermday', get_string('autumnstartday', 'local_eventocoursecreation'), $days);
        $mform->addHelpButton('starttimeautumntermday', 'autumnstartday', 'local_eventocoursecreation');
        $mform->setDefault('starttimeautumntermday', $config->starttimeautumntermday);
        $mform->addElement('select', 'starttimeautumntermmonth', get_string('autumnstartmonth', 'local_eventocoursecreation'), $months);
        $mform->addHelpButton('starttimeautumntermmonth', 'autumnstartmonth', 'local_eventocoursecreation');
        $mform->setDefault('starttimeautumntermmonth', $config->starttimeautumntermmonth);
        $mform->addElement('advcheckbox', 'execonlyonstarttimeautumnterm', get_string('execonlyonstarttimeautumnterm', 'local_eventocoursecreation'),
                            '', null, array(0, 1));
        $mform->setDefault('execonlyonstarttimeautumnterm', $config->execonlyonstarttimeautumnterm);
        $mform->addHelpButton('execonlyonstarttimeautumnterm', 'execonlyonstarttimeautumnterm', 'local_eventocoursecreation');

        // Hidden Params.
        $mform->addElement('hidden', 'category', 0);
        $mform->setType('category', PARAM_INT);
        $mform->setDefault('category', $categoryid);

        $mform->addElement('hidden', 'contextid', 0);
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $contextid);

        // Bulk creation section
        $mform->addElement('header', 'runnowheader', get_string('bulkcreation', 'local_eventocoursecreation'));

        $bulkgroup = array();
        $bulkgroup[] = $mform->createElement('submit', 'runnow', get_string('createall', 'local_eventocoursecreation'));
        $bulkgroup[] = $mform->createElement('checkbox', 'force', get_string('forcecreation', 'local_eventocoursecreation'));
        $mform->addGroup($bulkgroup, 'bulkgrp', get_string('bulkcreationdesc', 'local_eventocoursecreation'), array(' '), false);
        $mform->addHelpButton('bulkgrp', 'bulkcreationdesc', 'local_eventocoursecreation');

        // Individual creation section (starts collapsed)
        $mform->addElement('header', 'individualcreationheader', get_string('individualcreation', 'local_eventocoursecreation'));
        $mform->setExpanded('individualcreationheader', false);

        // Add a container for the preview content with initial loading state
        $loading_html = html_writer::div(
            html_writer::div(
                get_string('loadingcourselist', 'local_eventocoursecreation'),
                'alert alert-info'
            ),
            '',
            array('id' => 'evento-preview-content')
        );
        $mform->addElement('html', $loading_html);

        $this->add_action_buttons();

        // Initialize JavaScript
        $PAGE->requires->strings_for_js([
            'select', 'force', 'create', 'event', 'category', 'dates', 'creating',
            'creationsuccessful', 'creationfailed', 'creationsuccessfulcount', 'creationfailedcount',
            'nocoursestocreate'
        ], 'local_eventocoursecreation');

        $PAGE->requires->js_call_amd('local_eventocoursecreation/preview', 'init', array($categoryid));

    }

    // Validate the form.
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        if (!empty($data['idnumber'])) {
            if ($existing = $DB->get_record('course_categories', array('idnumber' => $data['idnumber']))) {
                if (!$data['category'] || $existing->id != $data['category']) {
                    $errors['idnumber'] = get_string('categoryidnumbertaken', 'error');
                }
            }
        }

        // Course start
        if (!is_int($data['starttimecourse'])) {
            $errors['starttimecourse'] = get_string('starttimecourseinvalid', 'local_eventocoursecreation');
        }

        // Day.
        if ((int)($data['starttimespringtermday'] < 1) || (int)($data['starttimespringtermday'] > 31)) {
            $errors['starttimespringtermday'] = get_string('dayinvalid', 'local_eventocoursecreation');
        }

        if ((int)($data['starttimeautumntermday'] < 1) || (int)($data['starttimeautumntermday'] > 31)) {
            $errors['starttimeautumntermday'] = get_string('dayinvalid', 'local_eventocoursecreation');
        }

        // Month.
        if (((int)$data['starttimespringtermmonth'] < 1) || (int)($data['starttimespringtermmonth'] > 12)) {
            $errors['starttimespringtermmonth'] = get_string('monthinvalid', 'local_eventocoursecreation');
        }

        if ((int)($data['starttimeautumntermmonth'] < 1) || (int)($data['starttimeautumntermmonth'] > 12)) {
            $errors['starttimeautumntermmonth'] = get_string('monthinvalid', 'local_eventocoursecreation');
        }

        return $errors;
    }

    /**
     * Process the form data
     * @param array $data submitted form data
     * @return boolean true if normal save, false if alternative processing needed
     */
    public function process($data) {
        if (!empty($data->runnow)) {
            // Return false to indicate alternate processing needed
            return false;
        }
        // Normal save
        return true;
    }
    
    /**
     * Get appropriate status message based on runner status code
     *
     * @param int $status The status code from the runner
     * @return string The status message to display
     */
    private function get_status_message($status) {
        switch ($status) {
            case 0:
                $class = 'success';
                $message = get_string('creationsuccessful', 'local_eventocoursecreation');
                break;
            case 1:
                $class = 'error';
                $message = get_string('creationfailed', 'local_eventocoursecreation');
                break;
            case 2:
                $class = 'warning';
                $message = get_string('creationskipped', 'local_eventocoursecreation');
                break;
            default:
                $class = 'error';
                $message = get_string('creationunknown', 'local_eventocoursecreation');
        }
        
        return html_writer::div($message, 'alert alert-' . $class);
    }

    /**
     * Process the form before display
     */
    public function definition_after_data() {
        global $OUTPUT;
        $mform = $this->_form;
        
        // Check if this was a 'run now' submission
        if (optional_param('runnow', '', PARAM_TEXT)) {
            $force = optional_param('force', 0, PARAM_INT);
            
            // Output starts here - clear any previous output
            ob_clean();
            
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('runningcoursecreation', 'local_eventocoursecreation'));
            
            echo html_writer::start_div('progress-container');
            echo html_writer::start_div('progress-output');
            
            // Create tracer and run
            $trace = new html_list_progress_trace();
            $creator = new local_eventocoursecreation_course_creation();
            
            // Run sync for this category
            $categoryid = required_param('category', PARAM_INT);
            $status = $creator->course_sync($trace, $categoryid, $force);
            
            echo html_writer::end_div(); // progress-output
            
            // Add status message
            switch ($status) {
                case 0:
                    \core\notification::success(get_string('creationsuccessful', 'local_eventocoursecreation'));
                    break;
                case 1:
                    \core\notification::error(get_string('creationfailed', 'local_eventocoursecreation'));
                    break;
                case 2:
                    \core\notification::warning(get_string('creationskipped', 'local_eventocoursecreation'));
                    break;
                default:
                    \core\notification::error(get_string('creationunknown', 'local_eventocoursecreation'));
            }
            
            echo html_writer::end_div(); // progress-container
            
            // Display form again
            parent::definition_after_data();
            
            echo $OUTPUT->footer();
            die();
        }
    }
}