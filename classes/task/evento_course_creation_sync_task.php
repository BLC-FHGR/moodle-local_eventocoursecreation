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


namespace local_eventocoursecreation\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task for Evento course creation sync
 */
class evento_course_creation_sync_task extends \core\task\scheduled_task {
    /**
     * Get the name of the task
     *
     * @return string
     */
    public function get_name() {
        return get_string('eventocoursesynctask', 'local_eventocoursecreation');
    }

    /**
     * Execute the task
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/local/eventocoursecreation/classes/course_creation.php');
        
        mtrace("Starting evento course creation sync task...");
        
        try {
            print_r("helllll");
            $trace = new \text_progress_trace();
            $creation = new \local_eventocoursecreation_course_creation();
            $creation->set_trace($trace);
            print_r("please");
            $result = $creation->course_sync($trace);
            print_r("cheese");
            
            if ($result === 0) {
                mtrace("Sync completed successfully");
            } else {
                mtrace("Sync completed with status: " . $result);
            }
            
        } catch (\Exception $e) {
            mtrace("Error during sync: " . $e->getMessage());
            throw $e;
        }
    }
}