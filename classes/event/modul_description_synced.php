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

namespace local_eventocoursecreation\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event fired whenever the module description of a course has been looked at.
 *
 * The event carries the decision and its reason, so the log answers why a page was
 * written, why it was left alone and why a page that was edited by hand was reset.
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array $other {
 *     Extra information about the event.
 *
 *     - string action: the decision, one of the ACTION_ constants of modul_description
 *     - string reason: a short explanation of the decision
 *     - string anlassnummer: the evento event number the description was requested with
 *     - int|null cmid: the course module of the page, null if there is none
 *     - int|null idmb: the evento id of the description
 *     - string|null mbversion: the evento version of the description
 * }
 */
class modul_description_synced extends \core\event\base {

    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the localised name of the event.
     *
     * @return string the name
     */
    public static function get_name() {
        return get_string('eventmoduldescriptionsynced', 'local_eventocoursecreation');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string the description
     */
    public function get_description() {
        $action = s($this->other['action']);
        $reason = s($this->other['reason']);
        $anlassnummer = s($this->other['anlassnummer']);

        return "The module description synchronisation of the course with id '{$this->courseid}' "
            . "decided '{$action}' for the evento event number '{$anlassnummer}': {$reason}.";
    }

    /**
     * Returns the url of the course the event belongs to.
     *
     * @return \moodle_url the url
     */
    public function get_url() {
        return new \moodle_url('/course/view.php', array('id' => $this->courseid));
    }

    /**
     * Checks that the event carries everything the description needs.
     *
     * @return void
     * @throws \coding_exception if a mandatory value is missing
     */
    protected function validate_data() {
        parent::validate_data();

        foreach (array('action', 'reason', 'anlassnummer') as $key) {
            if (!isset($this->other[$key])) {
                throw new \coding_exception("The '{$key}' value must be set in other.");
            }
        }
    }
}
