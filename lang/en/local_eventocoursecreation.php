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
$string['individualcreatoin'] = 'Individual creation';
$string['foundcoursestocreate'] = 'Found {$a} courses available for creation';
$string['statusready'] = 'Ready';
$string['statusblocked'] = 'Blocked';
$string['eventdatesmissing'] = 'Event dates are missing';
$string['eventendpast'] = 'Event has already ended';
$string['eventissubevent'] = 'This is a sub-event';
$string['coursealreadyexists'] = 'Course already exists';
$string['notincreationperiod'] = 'Not in course creation period';
$string['previewgeneratedsuccess'] = '{$a} courses available for creation';
$string['previewfailed'] = 'Preview generation failed';
$string['loadingcourselist'] = 'Loading course list...';
$string['loading'] = 'Loading...';
$string['nocoursestocreate'] = 'No courses available to create';
$string['previewerror'] = 'Error generating preview';
$string['previewloadfailed'] = 'Failed to load preview data';
$string['creating'] = 'Creating...';
$string['creationsuccessful'] = 'Course created successfully';
$string['creationfailed'] = 'Course creation failed';
$string['coursecreated'] = 'Course created successfully';
$string['coursecreationfailed'] = 'Course creation failed';
$string['creationsuccessfulcount'] = '{$a} courses created successfully';
$string['creationfailedcount'] = '{$a} courses failed to create';
$string['categorynotenabledforevento'] = 'This category is not enabled for Evento course creation';
$string['connectionfailed'] = 'Failed to connect to Evento service';
$string['categoryhasnoidentifier'] = 'Category has no identifier';
$string['noeventsfound'] = 'No events found for this category';
$string['subcourses'] = 'Sub-courses';
$string['filterall'] = 'All';
$string['filterready'] = 'Ready';
$string['filterblocked'] = 'Blocked';
$string['sortby'] = 'Sort by';
$string['sortstatus'] = 'Status';
$string['sortdate'] = 'Date';
$string['sortname'] = 'Name';
$string['selectalleligible'] = 'Select all eligible';
$string['forceall'] = 'Force all';
$string['selected'] = 'selected';
$string['select'] = 'Select';
$string['force'] = 'Force';
$string['create'] = 'Create';
$string['createselected'] = 'Create selected';
$string['eventissubevent'] = 'This is a sub-event';
$string['parentcoursenotexists'] = 'Parent course must be created first';
$string['parentcourseexists'] = 'Parent course exists';
$string['notincreationperiod'] = 'Outside of allowed creation period';
$string['subevents'] = 'Sub-events';
$string['forcecreation'] = 'Force creation';
$string['forcecreation_help'] = 'Force creation bypasses normal restrictions like waiting for the creation period or requiring a parent course.';
// Smart Event Fetcher settings
$string['fetching_heading'] = 'Event fetching settings';
$string['fetching_heading_desc'] = 'Configure how events are fetched from the Evento API';
$string['fetching_mode'] = 'Fetching mode';
$string['fetching_mode_desc'] = 'Select which method to use for fetching events from Evento';
$string['fetching_mode_classic'] = 'Classic (original method)';
$string['fetching_mode_smart'] = 'Smart (adaptive fetching)';
$string['fetching_mode_fast'] = 'Fast (incremental updates)';
$string['fetching_mode_parallel'] = 'Parallel (experimental)';
$string['batch_size'] = 'Batch size';
$string['batch_size_desc'] = 'Number of events to fetch in each API request';
$string['min_batch_size'] = 'Minimum batch size';
$string['min_batch_size_desc'] = 'Minimum number of events to fetch when reducing batch size';
$string['max_batch_size'] = 'Maximum batch size';
$string['max_batch_size_desc'] = 'Maximum number of events to fetch when increasing batch size';
$string['adaptive_batch_sizing'] = 'Adaptive batch sizing';
$string['adaptive_batch_sizing_desc'] = 'Dynamically adjust batch size based on API response';
$string['date_chunk_fallback'] = 'Date chunk fallback';
$string['date_chunk_fallback_desc'] = 'Fall back to fetching by date chunks if pagination fails';
$string['date_chunk_days'] = 'Date chunk size (days)';
$string['date_chunk_days_desc'] = 'Size of date chunks in days when using date chunking';
$string['max_api_retries'] = 'Max API retries';
$string['max_api_retries_desc'] = 'Maximum number of retries for failed API requests';
$string['cache_ttl'] = 'Cache time to live';
$string['cache_ttl_desc'] = 'How long to cache API responses in seconds';

// Parallel processing settings
$string['parallel_heading'] = 'Parallel processing settings (experimental)';
$string['parallel_heading_desc'] = 'Configure parallel event fetching (requires CLI)';
$string['parallel_requests'] = 'Enable parallel requests';
$string['parallel_requests_desc'] = 'Process API requests in parallel (only in CLI mode)';
$string['max_parallel_threads'] = 'Maximum parallel threads';
$string['max_parallel_threads_desc'] = 'Maximum number of parallel worker processes';
$string['parallel_requires_cli'] = 'Parallel processing requires CLI mode';

// Cache maintenance task
$string['eventocachemaintenance'] = 'Evento cache maintenance';
$string['fastmodesync'] = 'Fast mode synchronization';

// API Monitor page
$string['apimonitor'] = 'API Monitor';
$string['apistats'] = 'API Statistics';
$string['configsummary'] = 'Configuration Summary';
$string['statstotals'] = 'Overall Statistics';
$string['api_calls'] = 'API Calls';
$string['api_errors'] = 'Errors';
$string['api_cache_hits'] = 'Cache Hits';
$string['api_last_run'] = 'Last Run';
$string['cachepurged'] = 'Cache has been purged';
$string['purge_cache'] = 'Purge Cache';
$string['currentmode'] = 'Current Mode';
$string['setting'] = 'Setting';
$string['value'] = 'Value';
$string['veranstalterdetails'] = 'Veranstalter Details';
$string['veranstalter'] = 'Veranstalter';
$string['error_rate'] = 'Error Rate';
$string['change_settings'] = 'Change Settings';

// Debug page
$string['debugpage'] = 'Debug Tools';
$string['testconnection'] = 'Test Connection';
$string['connectionsuccessful'] = 'Connection successful';
$string['connectionfailed'] = 'Connection failed';
$string['fetchevents'] = 'Fetch Events';
$string['selectveranstalter'] = 'Select a Veranstalter';
$string['maxresults'] = 'Max Results';
$string['fetchmode'] = 'Fetch Mode';
$string['selectfetchmode'] = 'Select a fetch mode';
$string['fetchedevents'] = '{$a} events fetched';
$string['noevents'] = 'No events found';
$string['eventid'] = 'Event ID';
$string['eventnumber'] = 'Event Number';
$string['eventname'] = 'Event Name';
$string['startdate'] = 'Start Date';
$string['enddate'] = 'End Date';
$string['errorveranstalterlist'] = 'Error fetching Veranstalter list';
$string['traceoutput'] = 'Trace Output';
$string['invalidfetchmode'] = 'Invalid fetch mode';
$string['experimental'] = '(experimental)';
$string['reset_hwm'] = 'Reset high watermark (for Fast Mode)';
$string['hwm_reset'] = 'High watermark has been reset';
// Global API settings
$string['eventofetching'] = 'Event fetching';
$string['eventofetching_help'] = 'Configure how events are fetched from the Evento API';
$string['fetching_mode'] = 'Fetching mode';
$string['fetching_mode_desc'] = 'Select which method to use for fetching events from Evento';
$string['fetching_mode_classic'] = 'Classic (original method)';
$string['fetching_mode_smart'] = 'Smart (adaptive fetching)';
$string['fetching_mode_fast'] = 'Fast (incremental updates)';
$string['fetching_mode_parallel'] = 'Parallel (experimental)';
$string['batch_size'] = 'Batch size';
$string['batch_size_desc'] = 'Number of events to fetch in each API request';
$string['min_batch_size'] = 'Minimum batch size';
$string['min_batch_size_desc'] = 'Minimum number of events to fetch when reducing batch size';
$string['max_batch_size'] = 'Maximum batch size';
$string['max_batch_size_desc'] = 'Maximum number of events to fetch when increasing batch size';
$string['adaptive_batch_sizing'] = 'Adaptive batch sizing';
$string['adaptive_batch_sizing_desc'] = 'Dynamically adjust batch size based on API response';
$string['date_chunk_fallback'] = 'Date chunk fallback';
$string['date_chunk_fallback_desc'] = 'Fall back to fetching by date chunks if pagination fails';
$string['date_chunk_days'] = 'Date chunk size (days)';
$string['date_chunk_days_desc'] = 'Size of date chunks in days when using date chunking';
$string['max_api_retries'] = 'Max API retries';
$string['max_api_retries_desc'] = 'Maximum number of retries for failed API requests';
$string['cache_ttl'] = 'Cache time to live';
$string['cache_ttl_desc'] = 'How long to cache API responses in seconds';

// Category-specific API settings
$string['apifetchingheader'] = 'API Fetching Settings';
$string['override_global_fetching'] = 'Override global fetching settings';
$string['override_global_fetching_help'] = 'Use category-specific settings instead of global settings';
$string['custom_batch_size'] = 'Custom batch size';
$string['custom_batch_size_help'] = 'Custom batch size for this category (0 = use global setting)';
$string['current_global_settings'] = 'Current global settings';

// Admin pages
$string['apimonitor'] = 'API Monitor';
$string['debugpage'] = 'Debug Tools';
$string['purge_cache'] = 'Purge Cache';
$string['cachepurged'] = 'Cache has been purged';
$string['api_calls'] = 'API Calls';
$string['api_errors'] = 'Errors';
$string['api_cache_hits'] = 'Cache Hits';
$string['api_last_run'] = 'Last Run';
$string['veranstalter'] = 'Veranstalter';
$string['error_rate'] = 'Error Rate';
$string['fetchevents'] = 'Fetch Events';
$string['noevents'] = 'No events found';
$string['reset_hwm'] = 'Reset high watermark (for Fast Mode)';
$string['hwm_reset'] = 'High watermark has been reset';
$string['experimental'] = '(experimental)';

// Error messages and status strings
$string['requiredclassesnotfound'] = 'Required classes not found. Make sure you have run the Moodle upgrade process.';
$string['cachesnotinstalled'] = 'Cache definitions not installed. Run Moodle upgrade to install the required caches.';
$string['selectveranstalter'] = 'Select a Veranstalter';
$string['selectfetchmode'] = 'Select a fetch mode';
$string['maxresults'] = 'Max Results';
$string['eventid'] = 'Event ID';
$string['eventnumber'] = 'Event Number';
$string['eventname'] = 'Event Name';
$string['startdate'] = 'Start Date';
$string['enddate'] = 'End Date';
$string['testconnection'] = 'Test Connection';
$string['connectionsuccessful'] = 'Connection successful';
$string['connectionfailed'] = 'Connection failed';
$string['traceoutput'] = 'Trace Output';
$string['invalidfetchmode'] = 'Invalid fetch mode';
$string['errorveranstalterlist'] = 'Error fetching Veranstalter list';
$string['veranstalterdetails'] = 'Veranstalter Details';
$string['apistats'] = 'API Statistics';
$string['configsummary'] = 'Configuration Summary';
$string['statstotals'] = 'Overall Statistics';
$string['change_settings'] = 'Change Settings';
$string['setting'] = 'Setting';
$string['value'] = 'Value';
$string['currentmode'] = 'Current Mode';

// Setting Page
$string['evento_settings_unavailable_for_subcategory'] = 'Evento settings are not available for semester/year subcategories.';
$string['evento_settings_edit_parent'] = 'Please use the <a href="{$a->url}">settings of {$a->category}</a> instead.';