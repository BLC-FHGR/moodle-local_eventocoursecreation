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
 * @copyright  2017 HTW Chur Roger Barras
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/eventocoursecreation/locallib.php');

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_eventocoursecreation', get_string('pluginname', 'local_eventocoursecreation'));
    $ADMIN->add('localplugins', $settings);
    // General Settings.
    $settings->add(new admin_setting_heading('local_eventocoursecreation_settings', '',
                    get_string('pluginname_desc', 'local_eventocoursecreation')));
    $settings->add(new admin_setting_configcheckbox('local_eventocoursecreation/enableplugin',
        new lang_string('enableplugin', 'local_eventocoursecreation'),
        new lang_string('enableplugin_help', 'local_eventocoursecreation'), 1));

    // Long name setting for courses.
    $settings->add(new admin_setting_configtext('local_eventocoursecreation/longcoursenaming',
                    get_string('longcoursenaming', 'local_eventocoursecreation'),
                    get_string('longcoursenaming_help', 'local_eventocoursecreation'),
                    EVENTOCOURSECREATION_NAME_LONGNAME, PARAM_TEXT));
    // Short name setting for courses.
    $settings->add(new admin_setting_configtext('local_eventocoursecreation/shortcoursenaming',
                    get_string('shortcoursenaming', 'local_eventocoursecreation'),
                    get_string('shortcoursenaming_help', 'local_eventocoursecreation'),
                    EVENTOCOURSECREATION_NAME_SHORTNAME, PARAM_TEXT));

    // Default values for course setting.
    $settings->add(new admin_setting_heading('defaultcourssettings',
                    get_string('defaultcourssettings', 'local_eventocoursecreation'),
                    get_string('defaultcourssettings_help', 'local_eventocoursecreation')));

    $settings->add(new admin_setting_configcheckbox('local_eventocoursecreation/coursevisibility',
                    get_string('coursevisibility'),
                    get_string('coursevisibility_help'), 0));

    $settings->add(new admin_setting_configtext('local_eventocoursecreation/newsitemsnumber',
                    get_string('newsitemsnumber'),
                    get_string('newsitemsnumber_help'), 0, PARAM_INT));

    $settings->add(new admin_setting_configtext('local_eventocoursecreation/numberofsections',
                    get_string('numberofsections', 'local_eventocoursecreation'),
                    get_string('numberofsections_help', 'local_eventocoursecreation'), 0, PARAM_INT));

    // Days array.
    $days = array_combine(range(1, 31), range(1, 31));

    // Months array.
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

    // Spring term.
    $settings->add(new admin_setting_heading('startspringterm', get_string('startspringterm', 'local_eventocoursecreation'),
                   get_string('startspringterm_help', 'local_eventocoursecreation')));
    // Start day to create courses for the spring term.
    $settings->add(new admin_setting_configselect('local_eventocoursecreation/starttimespringtermday',
                    get_string('springstartday', 'local_eventocoursecreation'),
                    get_string('springstartday_help', 'local_eventocoursecreation'),
                    EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_STARTDAY, $days));
    // Start month to create courses for the spring term.
    $settings->add(new admin_setting_configselect('local_eventocoursecreation/starttimespringtermmonth',
                    get_string('springstartmonth', 'local_eventocoursecreation'),
                    get_string('springstartmonth_help', 'local_eventocoursecreation'),
                    EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_STARTMONTH, $months));
    // End day to create courses for the spring term.
    $settings->add(new admin_setting_configselect('local_eventocoursecreation/endtimespringtermday',
                    get_string('springendday', 'local_eventocoursecreation'),
                    get_string('springendday_help', 'local_eventocoursecreation'),
                    EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_ENDDAY, $days));
    // End month to create courses for the spring term.
    $settings->add(new admin_setting_configselect('local_eventocoursecreation/endtimespringtermmonth',
                    get_string('springendmonth', 'local_eventocoursecreation'),
                    get_string('springendmonth_help', 'local_eventocoursecreation'),
                    EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_ENDMONTH, $months));
    // Flag that the course creation is executed only on the start time and not from start time to the begin of the term.
    $settings->add(new admin_setting_configcheckbox('local_eventocoursecreation/execonlyonstarttimespringterm',
                    new lang_string('execonlyonstarttimespringterm', 'local_eventocoursecreation'),
                    new lang_string('execonlyonstarttimespringterm_help', 'local_eventocoursecreation'), 1));
    // Autumn term.
    $settings->add(new admin_setting_heading('startautumnterm', get_string('startautumnterm', 'local_eventocoursecreation'),
                    get_string('startautumnterm_help', 'local_eventocoursecreation')));
    // Start day to create courses for the autumn term.
    $settings->add(new admin_setting_configselect('local_eventocoursecreation/starttimeautumntermday',
                    get_string('autumnstartday', 'local_eventocoursecreation'),
                    get_string('autumnstartday_help', 'local_eventocoursecreation'),
                    EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_STARTDAY, $days));
    // Start month to create courses for the autumn term.
    $settings->add(new admin_setting_configselect('local_eventocoursecreation/starttimeautumntermmonth',
                    get_string('autumnstartmonth', 'local_eventocoursecreation'),
                    get_string('autumnstartmonth_help', 'local_eventocoursecreation'),
                    EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_STARTMONTH, $months));
    // End day to create courses for the autumn term.
    $settings->add(new admin_setting_configselect('local_eventocoursecreation/endtimeautumntermday',
                    get_string('autumnendday', 'local_eventocoursecreation'),
                    get_string('autumnendday_help', 'local_eventocoursecreation'),
                    EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_ENDDAY, $days));
    // End month to create courses for the autumn term.
    $settings->add(new admin_setting_configselect('local_eventocoursecreation/endtimeautumntermmonth',
                    get_string('autumnendmonth', 'local_eventocoursecreation'),
                    get_string('autumnendmonth_help', 'local_eventocoursecreation'),
                    EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_ENDMONTH, $months));
    // Flag that the course creation is executed only on the start time and not from start time to the begin of the term.
    $settings->add(new admin_setting_configcheckbox('local_eventocoursecreation/execonlyonstarttimeautumnterm',
                    new lang_string('execonlyonstarttimeautumnterm', 'local_eventocoursecreation'),
                    new lang_string('execonlyonstarttimeautumnterm_help', 'local_eventocoursecreation'), 1));
}
