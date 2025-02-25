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
 * Strings for component 'local_eventocoursecreation', language 'en'
 *
 * @package    local_eventocoursecreation
 * @copyright  2018, HTW chur {@link http://www.htwchur.ch}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['pluginname'] = 'Evento Course Creation';
$string['taskname'] = 'Evento Course Creation Synchronization';
$string['eventocoursesynctask'] = 'Evento course creation synchronisation';
$string['autumnendmonth'] = 'End month';
$string['autumnendmonth_help'] = 'Month, to which the course creation will be executed for the autumn term.';
$string['autumnendday'] = 'End day';
$string['autumnendday_help'] = 'Day, to which the course creation will be executed for the autumn term.';
$string['autumnstartday'] = 'Start day';
$string['autumnstartday_help'] = 'Day, from which the course creation will be executed for the autumn term. (only values between 1 to 31 are allowed)';
$string['autumnstartmonth'] = 'Start month';
$string['autumnstartmonth_help'] = 'Month, in which the course creation will be executed for the autumn term. (only values between 1 to 12 are allowed)';
$string['coursetorestorefromdoesnotexist'] = 'The course to restore from does not exist';
$string['dayinvalid'] = 'Day is not a valid day of a month (only values between 1 to 31 are allowed)';
$string['defaultcourssettings'] = 'Default course settings';
$string['defaultcourssettings_help'] = 'Default values for the course setting of new courses';
$string['disabled'] = 'Disabled';
$string['editcreationsettings'] = 'Edit Evento course creation settings ';
$string['enabled'] = 'Enabled';
$string['enablecatcoursecreation'] = 'Enable course creation';
$string['enablecatcoursecreation_help'] = 'Enable course creation for this category';
$string['enablecoursetemplate'] = 'Use course template';
$string['enablecoursetemplate_help'] = 'If enabled, the new courses will be created based on the choosen template course.';
$string['enableplugin'] = 'Enable Plugin';
$string['enableplugin_help'] = 'Enable or disable the plugin';
$string['eventosynccoursecreation'] = 'Evento course creation synchronisation';
$string['execonlyonstarttimeautumnterm'] = 'Execute the course creation only on the start date';
$string['execonlyonstarttimeautumnterm_help'] = 'If set, the course creation will be executed only on the start date. Otherwise, the course creation will be run until shortly after the start of the term, if there are new courses.';
$string['execonlyonstarttimespringterm'] = 'Execute the course creation only on the start date';
$string['execonlyonstarttimespringterm_help'] = 'If set, the course creation will be executed only on the start date. Otherwise, the course creation will be run until shortly after the start of the term, if there are new courses.';
$string['idnumber'] = 'Course of studies (category ID)';
$string['idnumber_help'] = 'Course of studies, which is stored in the cateogry ID. It has to be the prefix of the evento event number such like mod.dbm or mod.bsp or mod.tou, otherwise it will not work. Only courses with this prefix will be taken in account. Options with | and § will still work.';
$string['information'] = 'Information';
$string['longcoursenaming'] = 'Long name for moodle courses';
$string['longcoursenaming_help'] = 'Defines the long name for courses. Available tokens are: (Evento module name: @EVENTONAME@; Evento module abrevation: @EVENTOABK@; Term period: @PERIODE@; Course of studies: @STG@; Implementing number: @NUM@)';
$string['monthinvalid'] = 'Month is invalid (only values between 1 to 12 are allowed)';
$string['no'] = 'No';
$string['numberofsections'] = 'Number of sections';
$string['numberofsections_help'] = 'Number of section in empty new courses';
$string['plugindisabled'] = 'The plugin for Evento Course Creation is disabled!';
$string['pluginname_desc'] = 'Creates courses based on the evento modules.';
$string['privacy:metadata'] = 'The plugin Evento Course Creation does not store any personal data.';
$string['shortcoursenaming'] = 'Short name for moodle courses';
$string['shortcoursenaming_help'] = 'Defines the short name for courses. Available tokens are: (Evento module name: @EVENTONAME@; Evento module abrevation: @EVENTOABK@; Term period: @PERIODE@; Course of studies: @STG@; Implementing number: @NUM@)';
$string['springendmonth'] = 'End month';
$string['springendmonth_help'] = 'Month, to which the course creation will be executed for the spring term.';
$string['springendday'] = 'End day';
$string['springendday_help'] = 'Day, to which the course creation will be executed for the spring term.';
$string['springstartday'] = 'Start day';
$string['springstartday_help'] = 'Day, from which the course creation will be executed for the spring term. (only values between 1 to 31 are allowed)';
$string['springstartmonth'] = 'Start month';
$string['springstartmonth_help'] = 'Month, in which the course creation will be executed for the spring term. (only values between 1 to 12 are allowed)';
$string['startautumnterm'] = 'Autumn term';
$string['startautumnterm_help'] = 'Default values for the start time of the autumn term';
$string['startspringterm'] = 'Spring term';
$string['startspringterm_help'] = 'Default values for the start time of the spring term';
$string['templatecourse'] = 'Template course';
$string['templatecourse_help'] = 'Template course, for new courses to be created. If no template is selected the new courses are empty.';
$string['yes'] = 'Yes';
$string['january'] = 'January';
$string['february'] = 'February';
$string['march'] = 'March';
$string['april'] = 'April';
$string['may'] = 'May';
$string['june'] = 'June';
$string['july'] = 'July';
$string['august'] = 'August';
$string['september'] = 'September';
$string['october'] = 'October';
$string['november'] = 'November';
$string['december'] = 'December';
$string['customcoursesettings'] = 'Custom course settings';
$string['setcustomcoursestart'] = 'Set a custom course start date.';
$string['setcustomcoursestart_help'] = 'If set, the course will be created with this date as it\'s starting date. Otherwise, the course start date will be identical to the semester start.';
$string['coursestart'] = 'Start time';
$string['coursestart_help'] = 'Start time that will be set at course cration.';
$string['starttimecourseinvalid'] = 'Course start time is not a valid unix time stamp.';
$string['runnowheader'] = 'Run Course Creation';
$string['runnow'] = 'Create Courses Now';
$string['runnowdesc'] = 'Immediately create all courses from Evento for this category';
$string['runnowdesc_help'] = 'This will start the course creation process for this category immediately. Use the force option to bypass timing restrictions.';
$string['forcecreation'] = 'Force creation (ignore timing restrictions)';
$string['runningcoursecreation'] = 'Creating Evento Courses';
$string['creationsuccessful'] = 'Course creation completed successfully';
$string['creationfailed'] = 'Course creation failed';
$string['creationskipped'] = 'Course creation skipped - prerequisites not met';
$string['creationunknown'] = 'Unknown error during course creation';
$string['returntocategory'] = 'Return to category';
$string['defaultsubcatorganization'] = 'Default subcategory organization';
$string['defaultsubcatorganization_help'] = 'Choose how courses should be organized in subcategories by default';
$string['subcatorganization'] = 'Subcategory organization';
$string['subcatorganization_help'] = 'Choose how courses should be organized in subcategories for this category';
$string['subcatorg_none'] = 'No subcategories';
$string['subcatorg_semester'] = 'By semester (FS/HS)';
$string['subcatorg_year'] = 'By year';
// Preview functionality
$string['nocoursestocreate'] = 'No courses available to create';
$string['select'] = 'Select';
$string['subcourses'] = 'Related courses';
$string['create'] = 'Create';
$string['createselected'] = 'Create selected courses';
$string['forcecreation'] = 'Force creation';
$string['force'] = 'Force';
$string['create'] = 'Create';
$string['createselected'] = 'Create selected courses';
$string['creating'] = 'Creating courses...';
$string['coursepreview'] = 'Course Preview';
$string['creationsuccessfulcount'] = '{$a} courses were created successfully';
$string['creationfailedcount'] = '{$a} courses failed to create';
$string['categorynotfound'] = 'Category not found';
$string['settingsnotfound'] = 'Category settings not found';
$string['eventnotfound'] = 'Event not found in Evento';
$string['outsidecreationperiod'] = 'Outside allowed creation period';
$string['notmainevent'] = 'Not a main event';
$string['creationnotenabled'] = 'Course creation is not enabled for this category';
$string['creationnotallowed'] = 'Course creation is not allowed at this time';
$string['coursealreadyexists'] = 'Course already exists in Moodle';
$string['coursecreated'] = 'Course successfully created';
$string['prerequisitesfailed'] = 'System prerequisites not met';
$string['coursecreationfailed'] = 'Course creation failed';
$string['individualcreationheader'] = 'Individual Course Creation';
$string['previewloading'] = 'Loading course preview...';
$string['previewfailed'] = 'Failed to load course preview';
$string['previewunavailable'] = 'Preview unavailable - please check system settings';
$string['bulkcreation'] = 'Bulk creation';
$string['bulkcreationdesc'] = 'Create all the courses in this category';
$string['individualcreation'] = 'Individual creation';
$string['loadingcourselist'] = 'Loading available courses...';
$string['previewerror'] = 'Error displaying course preview';
$string['previewloadfailed'] = 'Failed to load course preview';