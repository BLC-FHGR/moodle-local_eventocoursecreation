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

use local_eventocoursecreation\modul_description_sync;

/**
 * Keeps the evento module descriptions of the existing courses up to date.
 *
 * This is the safety net under the adhoc import. It also catches every course whose
 * description changed in evento after the course had been created, and it restores
 * pages that were deleted or edited outside of this plugin.
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class evento_modul_description_sync_task extends \core\task\scheduled_task {

    /**
     * Returns the name shown to the administrators.
     *
     * @return string the name
     */
    public function get_name() {
        return get_string('eventosyncmoduldescription', 'local_eventocoursecreation');
    }

    /**
     * Runs one batch of the synchronisation.
     *
     * @return void
     */
    public function execute() {
        $sync = new modul_description_sync(new \text_progress_trace());
        $summary = $sync->sync_courses();

        foreach ($summary->actions as $action => $count) {
            mtrace('  ' . $action . ': ' . $count);
        }
    }
}
