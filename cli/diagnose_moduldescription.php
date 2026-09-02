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
 * Shows why the module description import did or did not happen.
 *
 * The script only reads, it never changes anything. Use
 * cli/sync_moduldescriptions.php to actually import a description.
 *
 * Usage help:
 * $ sudo -u www-data /usr/bin/php local/eventocoursecreation/cli/diagnose_moduldescription.php -h
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once("$CFG->libdir/clilib.php");

list($options, $unrecognized) = cli_get_params(
    array('courseid' => false, 'limit' => false, 'help' => false),
    array('h' => 'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    echo "Show why the evento module description import did or did not happen.

This script changes nothing. It reports the configuration, the registered event
observers, the waiting and the finished tasks and, for a single course, the
resolved event number, the managed page and the stored synchronisation state.

Options:
--courseid=ID         Also inspect this single course
--limit=N             Number of history entries to show per section, defaults to 10
-h, --help            Print out this help

Example:
\$ sudo -u www-data /usr/bin/php local/eventocoursecreation/cli/diagnose_moduldescription.php \\
      --courseid=42
";
    exit(0);
}

$limit = empty($options['limit']) ? 10 : max(1, (int)$options['limit']);
$adhocclass = '\local_eventocoursecreation\task\modul_description_course_task';
$scheduledclass = '\local_eventocoursecreation\task\evento_modul_description_sync_task';
$eventname = '\local_eventocoursecreation\event\modul_description_synced';
$haslog = $DB->get_manager()->table_exists('logstore_standard_log');

/**
 * Prints a section heading.
 *
 * @param string $title the heading
 * @return void
 */
function local_eventocoursecreation_diag_heading($title) {
    cli_writeln('');
    cli_writeln('== ' . $title . ' ==');
}

/**
 * Prints one label and value pair, aligned.
 *
 * @param string $label the label
 * @param string $value the value
 * @return void
 */
function local_eventocoursecreation_diag_line($label, $value) {
    cli_writeln('  ' . str_pad($label . ':', 22) . $value);
}

// The history sections read the standard log store and the task log. Both can be
// switched off site wide, in which case an empty section means nothing at all.
local_eventocoursecreation_diag_heading('Logging, so an empty history can be told apart from a switched off log');
$stores = (string)get_config('tool_log', 'enabled_stores');
local_eventocoursecreation_diag_line('log stores', $stores === '' ? 'not configured' : $stores);
local_eventocoursecreation_diag_line('standard log store',
    strpos($stores, 'logstore_standard') !== false && $haslog ? 'enabled' : 'DISABLED, the event history stays empty');
$logmode = isset($CFG->task_logmode) ? (string)$CFG->task_logmode : 'not set, everything is logged';
local_eventocoursecreation_diag_line('task_logmode', $logmode
    . (isset($CFG->task_logmode) && (int)$CFG->task_logmode === 0 ? ', task logging is off' : ''));

local_eventocoursecreation_diag_heading('Configuration');
$settings = \local_eventocoursecreation\modul_description::get_settings();
local_eventocoursecreation_diag_line('import enabled',
    $settings->enabled ? 'yes' : 'NO, nothing is imported and the observer returns at once');
local_eventocoursecreation_diag_line('scope', $settings->scope);
local_eventocoursecreation_diag_line('page name', $settings->pagename);
local_eventocoursecreation_diag_line('page id number', $settings->cmidnumber);
local_eventocoursecreation_diag_line('accepted status ids',
    empty($settings->allowedstatus) ? 'all' : implode(', ', $settings->allowedstatus));
local_eventocoursecreation_diag_line('future descriptions', $settings->futurevalid ? 'imported' : 'skipped');
local_eventocoursecreation_diag_line('page deleted by hand', $settings->ondelete);
local_eventocoursecreation_diag_line('page renamed by hand', $settings->onrename);
local_eventocoursecreation_diag_line('batch size', (string)$settings->batchsize);
local_eventocoursecreation_diag_line('retry delay in hours', (string)$settings->retryhours);

// A restored course never fires course_created, the import depends on this observer.
local_eventocoursecreation_diag_heading('Event observers of the plugin');
$found = false;
foreach (\core\event\manager::get_all_observers() as $observed => $observers) {
    foreach ($observers as $observer) {
        if (strpos($observer->callable, 'local_eventocoursecreation') === false) {
            continue;
        }
        cli_writeln('  ' . $observed);
        cli_writeln('    callable: ' . $observer->callable);
        cli_writeln('    internal: ' . ($observer->internal ? 'yes' : 'no'));
        $found = true;
    }
}
if (!$found) {
    cli_writeln('  none, db/events.php was not picked up, purge the caches');
}

local_eventocoursecreation_diag_heading('Scheduled task');
$scheduled = \core\task\manager::get_scheduled_task($scheduledclass);
if (!$scheduled) {
    cli_writeln('  not installed, run the upgrade');
} else {
    local_eventocoursecreation_diag_line('enabled', $scheduled->get_disabled() ? 'NO, it is disabled' : 'yes');
    local_eventocoursecreation_diag_line('schedule', $scheduled->get_minute() . ' ' . $scheduled->get_hour()
        . ' ' . $scheduled->get_day() . ' ' . $scheduled->get_month() . ' ' . $scheduled->get_day_of_week());
    local_eventocoursecreation_diag_line('last run',
        $scheduled->get_last_run_time() ? userdate($scheduled->get_last_run_time()) : 'never');
    local_eventocoursecreation_diag_line('next run',
        $scheduled->get_next_run_time() ? userdate($scheduled->get_next_run_time()) : 'unknown');
}

local_eventocoursecreation_diag_heading('Adhoc tasks waiting');
// Queued with a leading backslash by the task manager, unlike the task log below.
$tasks = $DB->get_records('task_adhoc', array('classname' => $adhocclass), 'id DESC');
if (empty($tasks)) {
    cli_writeln('  none waiting');
}
foreach ($tasks as $task) {
    cli_writeln('  id ' . $task->id . '  customdata ' . $task->customdata
        . '  nextrun ' . userdate($task->nextruntime) . '  faildelay ' . $task->faildelay);
}

local_eventocoursecreation_diag_heading('Last task runs of the plugin');
// Matched loosely on purpose. The task log stores the class name without the leading
// backslash, an exact match on the queued name would wrongly report an empty history.
$runs = $DB->get_records_select('task_log', $DB->sql_like('classname', ':needle'),
    array('needle' => '%eventocoursecreation%'), 'timestart DESC',
    'id, classname, timestart, timeend, result, output', 0, $limit);
if (empty($runs)) {
    cli_writeln('  no task of this plugin has run yet');
}
foreach ($runs as $run) {
    // A result of 0 means success.
    cli_writeln('  ' . userdate($run->timestart) . '  ' . ($run->result ? 'FAILED' : 'ok')
        . '  ' . $run->classname);
    // The output holds the mtrace lines and therefore the branch the run took.
    foreach (explode("\n", trim((string)$run->output)) as $line) {
        if (trim($line) !== '') {
            cli_writeln('      ' . rtrim($line));
        }
    }
}

local_eventocoursecreation_diag_heading('Last synchronisations across the site');
if (!$haslog) {
    cli_writeln('  the standard log store is not installed');
} else {
    $entries = $DB->get_records('logstore_standard_log', array('eventname' => $eventname),
        'timecreated DESC', 'id, courseid, timecreated, origin, other', 0, $limit);
    if (empty($entries)) {
        cli_writeln('  nothing has been synchronised yet');
    }
    foreach ($entries as $entry) {
        // The origin is cli for a task run as well, moodle knows no cron origin.
        cli_writeln('  ' . userdate($entry->timecreated) . '  course=' . $entry->courseid
            . '  origin=' . $entry->origin . '  ' . $entry->other);
    }
}

if (empty($options['courseid'])) {
    cli_writeln('');
    cli_writeln('Pass --courseid=ID to also inspect a single course.');
    exit(0);
}

$course = $DB->get_record('course', array('id' => (int)$options['courseid']), '*', MUST_EXIST);

local_eventocoursecreation_diag_heading('Course ' . $course->id);
local_eventocoursecreation_diag_line('shortname', $course->shortname);
local_eventocoursecreation_diag_line('idnumber', $course->idnumber === '' ? "'' (empty)" : $course->idnumber);
local_eventocoursecreation_diag_line('startdate', $course->startdate ? userdate($course->startdate) : '0 (not set)');
local_eventocoursecreation_diag_line('enddate', $course->enddate ? userdate($course->enddate) : '0 (not set)');

$enrols = $DB->get_records('enrol', array('courseid' => $course->id, 'enrol' => 'evento'), 'id ASC',
    'id, status, customtext1');
local_eventocoursecreation_diag_line('evento enrolments', (string)count($enrols));
foreach ($enrols as $enrol) {
    cli_writeln('    id ' . $enrol->id . "  customtext1: '" . trim((string)$enrol->customtext1) . "'");
}

$anlassnummer = \local_eventocoursecreation\modul_description::resolve_anlassnummer($course);
local_eventocoursecreation_diag_line('event number',
    $anlassnummer ?? 'none, this course is skipped by the import');

$pages = \local_eventocoursecreation\modul_description::find_managed_pages($course->id, $settings->cmidnumber);
local_eventocoursecreation_diag_line('managed pages', (string)count($pages)
    . (count($pages) > 1 ? ', more than one, the oldest one is used' : ''));
foreach ($pages as $cm) {
    cli_writeln('    cmid ' . $cm->id . '  section ' . $cm->sectionnum . "  name '" . $cm->name
        . "'  idnumber '" . $cm->idnumber . "'  " . ($cm->visible ? 'visible' : 'hidden'));
}

$record = \local_eventocoursecreation\modul_description::get_link_record($course->id);
if (is_null($record)) {
    local_eventocoursecreation_diag_line('stored state', 'none, this course was never checked');
} else {
    $states = array(
        EVENTOCOURSECREATION_MB_STATUS_OK => 'ok',
        EVENTOCOURSECREATION_MB_STATUS_MISSING => 'missing, evento offers no usable description',
        EVENTOCOURSECREATION_MB_STATUS_ERROR => 'error',
        EVENTOCOURSECREATION_MB_STATUS_DISABLED => 'disabled, this course is no longer synchronised',
    );
    local_eventocoursecreation_diag_line('stored state',
        $states[$record->status] ?? ('unknown (' . $record->status . ')'));
    local_eventocoursecreation_diag_line('stored event number', (string)$record->anlassnummer);
    local_eventocoursecreation_diag_line('stored cmid', is_null($record->cmid) ? 'none' : (string)$record->cmid);
    local_eventocoursecreation_diag_line('idMB / idStatus',
        ($record->idmb ?? '-') . ' / ' . ($record->idstatus ?? '-'));
    local_eventocoursecreation_diag_line('version scaled', (string)($record->mbversionscaled ?? '-'));
    local_eventocoursecreation_diag_line('valid from',
        $record->mbgueltigab ? userdate($record->mbgueltigab) : 'not set');
    local_eventocoursecreation_diag_line('content hash', (string)($record->contenthash ?? '-'));
    local_eventocoursecreation_diag_line('last checked',
        $record->timechecked ? userdate($record->timechecked) : 'never');
    local_eventocoursecreation_diag_line('last written',
        $record->timemodified ? userdate($record->timemodified) : 'never');
    local_eventocoursecreation_diag_line('failures in a row', (string)$record->errorcount);
    if (!empty($record->lasterror)) {
        local_eventocoursecreation_diag_line('last error', $record->lasterror);
    }
}

// Asked without a limit, so a course far down the queue is still found.
$candidates = \local_eventocoursecreation\modul_description::get_sync_candidates($settings, null, 0);
local_eventocoursecreation_diag_line('in the sync scope', isset($candidates[$course->id])
    ? 'yes'
    : 'NO, the scheduled task ignores this course, use --courseid to import it anyway');

local_eventocoursecreation_diag_heading('History of this course');
if (!$haslog) {
    cli_writeln('  the standard log store is not installed');
} else {
    $restores = $DB->get_records('logstore_standard_log',
        array('eventname' => '\core\event\course_restored', 'courseid' => $course->id),
        'timecreated DESC', 'id, timecreated, origin', 0, $limit);
    if (empty($restores)) {
        cli_writeln('  no restore, this course was not created from a template or a backup');
    }
    foreach ($restores as $restore) {
        cli_writeln('  restored  ' . userdate($restore->timecreated) . '  origin=' . $restore->origin);
    }

    $entries = $DB->get_records('logstore_standard_log',
        array('eventname' => $eventname, 'courseid' => $course->id),
        'timecreated DESC', 'id, timecreated, origin, userid, other', 0, $limit);
    if (empty($entries)) {
        cli_writeln('  never synchronised through the plugin');
    }
    foreach ($entries as $entry) {
        cli_writeln('  synced    ' . userdate($entry->timecreated) . '  origin=' . $entry->origin
            . '  userid=' . $entry->userid);
        cli_writeln('            ' . $entry->other);
    }
}

cli_writeln('');
exit(0);
