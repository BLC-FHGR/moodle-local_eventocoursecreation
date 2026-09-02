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
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventocoursecreation;

defined('MOODLE_INTERNAL') || die();

use local_eventocoursecreation\task\modul_description_course_task;

/**
 * Event observers of the plugin.
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Queues the module description import after a course has been restored.
     *
     * A course created through the restore interface never fires course_created, that
     * event only comes from create_course(); restore_dbops::create_new_course() inserts
     * the course record directly. Without this observer such a course would keep the
     * outdated page of the template it was copied from until the scheduled task happens
     * to reach it.
     *
     * The import itself runs in an adhoc task, so a slow webservice never delays the
     * restore, and the queueing is skipped for every course without an evento reference.
     *
     * @param \core\event\course_restored $event the event
     * @return void
     */
    public static function course_restored(\core\event\course_restored $event) {
        global $DB;

        // The event manager turns any exception raised in here into a debugging message,
        // see \core\event\manager::dispatch(). Catching it makes the reason visible in the
        // error log of the site instead of leaving a silently missing import behind.
        try {
            $settings = modul_description::get_settings();
            if (!$settings->enabled) {
                return;
            }

            $course = $DB->get_record('course', array('id' => $event->objectid));
            if (!$course) {
                return;
            }
            // A course restored into a new course loses its idnumber, because the original
            // still holds it. Such a copy only carries an evento reference when the
            // enrolment methods came along, otherwise it is none of our business.
            $anlassnummer = modul_description::resolve_anlassnummer($course);
            if (is_null($anlassnummer)) {
                // Silent in normal operation, but a restore that skipped the import has to be
                // explainable afterwards, and the observer leaves no other trace.
                debugging('local_eventocoursecreation: course ' . $course->id . ' carries no evento event '
                    . 'number after the restore, the module description import is not queued', DEBUG_DEVELOPER);
                return;
            }

            $queued = modul_description_course_task::queue_for_course($course->id);
            debugging('local_eventocoursecreation: module description import for course ' . $course->id
                . ' (' . $anlassnummer . ') ' . ($queued ? 'queued' : 'not queued, the feature is off'),
                DEBUG_DEVELOPER);
        } catch (\Throwable $ex) {
            error_log('local_eventocoursecreation: could not queue the module description import for course '
                . $event->objectid . ': ' . $ex->getMessage());
            debugging('Could not queue the module description import: ' . $ex->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
