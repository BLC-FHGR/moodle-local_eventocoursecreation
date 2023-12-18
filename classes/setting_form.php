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
        global $OUTPUT, $DB;
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
        // $mform->disabledIf('templatecourse', 'enablecoursetemplate', 'eq', 0);

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

        $this->add_action_buttons( true, get_string('savechanges'));

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
}