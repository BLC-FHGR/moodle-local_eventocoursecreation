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
 * Synchronises the evento module descriptions into the courses.
 *
 * Usage help:
 * $ sudo -u www-data /usr/bin/php local/eventocoursecreation/cli/sync_moduldescriptions.php -h
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once("$CFG->libdir/clilib.php");

// Run as the cron user, which is the admin. Without it $USER is nobody, and the
// capability checks inside add_moduleinfo() and update_moduleinfo() would silently
// skip the forced language and the visibility of the module.
\core\cron::setup_user();

list($options, $unrecognized) = cli_get_params(
    array('courseid' => false, 'limit' => false, 'list' => false, 'retry' => false,
        'verbose' => false, 'help' => false),
    array('v' => 'verbose', 'h' => 'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    echo "Synchronise the evento module descriptions into the courses.

Options:
--courseid=ID         Synchronise this single course, ignoring the configured scope
--limit=N             Process at most N courses, defaults to the configured batch size
--list                Only show which courses would be processed, change nothing
--retry                Also take courses which failed before and are still held back
-v, --verbose         Print progress information
-h, --help            Print out this help

Example:
\$ sudo -u www-data /usr/bin/php local/eventocoursecreation/cli/sync_moduldescriptions.php \\
      --courseid=42 --verbose
";
    exit(0);
}

$trace = empty($options['verbose']) ? new null_progress_trace() : new text_progress_trace();
$settings = \local_eventocoursecreation\modul_description::get_settings();

if (!$settings->enabled) {
    cli_problem(get_string('moduldescriptiondisabled', 'local_eventocoursecreation'));
}

$limit = empty($options['limit']) ? null : (int)$options['limit'];

if (!empty($options['retry'])) {
    // The backoff is a waiting time measured against timechecked, so dropping it to zero
    // brings the courses held back after a failure into the batch again.
    $settings->retryhours = 0;
}

if (!empty($options['list'])) {
    $courses = \local_eventocoursecreation\modul_description::get_sync_candidates($settings, null, $limit);
    cli_writeln(count($courses) . ' course(s) would be processed:');
    foreach ($courses as $course) {
        $anlassnummer = \local_eventocoursecreation\modul_description::resolve_anlassnummer($course);
        cli_writeln('  ' . $course->id . "\t" . $course->shortname . "\t" . ($anlassnummer ?? '-'));
    }
    exit(0);
}

// The settings are handed over, so that --retry reaches the candidate query of the run.
$sync = new \local_eventocoursecreation\modul_description_sync($trace, null, $settings);

try {
    if (!empty($options['courseid'])) {
        // A single course is synchronised on request, even when it is out of the configured scope.
        $course = $DB->get_record('course', array('id' => (int)$options['courseid']), '*', MUST_EXIST);
        $result = $sync->sync_course($course);
        cli_writeln("{$course->shortname}: {$result->action}, {$result->reason}");
        exit(0);
    }

    $summary = $sync->sync_courses($limit);
} catch (Throwable $ex) {
    // The default cli handler swallows the debug information unless debugging is on,
    // which turns every failure into an unusable one line message.
    cli_problem(get_class($ex) . ': ' . $ex->getMessage());
    if (!empty($ex->debuginfo)) {
        cli_problem('Debug info: ' . $ex->debuginfo);
    }
    cli_problem($ex->getTraceAsString());
    exit(1);
}

cli_writeln($summary->processed . ' course(s) processed');
foreach ($summary->actions as $action => $count) {
    cli_writeln('  ' . $action . "\t" . $count);
}
if ($summary->stopped) {
    cli_problem('The run was stopped early because the evento webservice is unavailable.');
    exit(1);
}

exit(0);
