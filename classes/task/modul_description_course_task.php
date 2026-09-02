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

namespace local_eventocoursecreation\task;

defined('MOODLE_INTERNAL') || die();

use local_eventocoursecreation\modul_description;
use local_eventocoursecreation\modul_description_sync;

/**
 * Imports the evento module description of a single course.
 *
 * Queued right after a course has been created, so the additional webservice call
 * never delays the course creation itself and a webservice timeout can never put the
 * new course at risk. A failure is retried by the task system on its own.
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modul_description_course_task extends \core\task\adhoc_task {

    /**
     * Queues the import of one course, unless the same import is already waiting.
     *
     * @param int $courseid the course to import the module description for
     * @return bool true if a task was queued
     */
    public static function queue_for_course($courseid): bool {
        $settings = modul_description::get_settings();
        if (!$settings->enabled) {
            return false;
        }

        $task = new self();
        $task->set_custom_data(array('courseid' => (int)$courseid));

        // The second argument makes the task system skip an identical task that is
        // already waiting, which happens when a course is both created and restored.
        \core\task\manager::queue_adhoc_task($task, true);

        return true;
    }

    /**
     * Runs the import.
     *
     * @return void
     * @throws \moodle_exception if the webservice is unavailable, so the task is retried
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $courseid = (int)($data->courseid ?? 0);
        if (empty($courseid)) {
            mtrace('No course id in the task data, nothing to do');
            return;
        }

        $settings = modul_description::get_settings();
        if (!$settings->enabled) {
            mtrace('The module description import is not enabled, skipping course ' . $courseid);
            return;
        }

        $course = $DB->get_record('course', array('id' => $courseid));
        if (!$course) {
            // The course was deleted between queueing and running, that is not a failure.
            mtrace('Course ' . $courseid . ' no longer exists, nothing to do');
            return;
        }

        $sync = new modul_description_sync(new \text_progress_trace(), null, $settings);
        $sync->sync_course($course);

        if ($sync->is_service_unavailable()) {
            // Fail on purpose, the task system retries with a growing delay.
            throw new \moodle_exception('moduldescriptionserviceunavailable', 'local_eventocoursecreation');
        }
    }
}
