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

// The class loader includes this file from inside a method, so $CFG is not in scope on its own.
global $CFG;
require_once($CFG->dirroot . '/local/eventocoursecreation/locallib.php');

use local_eventocoursecreation\event\modul_description_synced;

/**
 * Synchronises the evento module description into the courses.
 *
 * One course at a time, so that a single failure never stops the others. Only a fault
 * which says that the webservice itself is unavailable ends the whole run, there is no
 * point in asking a dead service several hundred times.
 *
 * @package    local_eventocoursecreation
 * @copyright  2026 FH Graubuenden
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class modul_description_sync {

    /** Soap fault codes which mean that the webservice as a whole is unavailable. */
    const STOP_FAULTCODES = array('HTTP', 'soapenv:Server', 'Server');

    /**
     * Marks in a soap fault which mean that evento simply has no description for this event.
     *
     * Evento answers such a request with a fault and not with an empty result, and it uses
     * the same fault code it uses for a real server problem. Only the message tells the two
     * apart, so it has to be read. Both marks are looked for, the wording because it is what
     * evento sends today, and the exception class because it survives a change of wording.
     */
    const NODESCRIPTION_MARKS = array('keine modulbeschreibung gefunden', 'dataretrievalexception');

    /** @var \progress_trace where the progress is written to. */
    protected $trace;

    /** @var \local_evento_evento_service the webservice wrapper. */
    protected $service;

    /** @var \stdClass the settings of this feature. */
    protected $settings;

    /** @var bool set once the webservice turned out to be unavailable. */
    protected $serviceunavailable = false;

    /**
     * Constructor.
     *
     * @param \progress_trace|null $trace where the progress is written to, silent by default
     * @param \local_evento_evento_service|null $service the webservice wrapper, built on demand
     * @param \stdClass|null $settings the settings, read from the configuration by default
     */
    public function __construct(?\progress_trace $trace = null, $service = null, $settings = null) {
        $this->trace = is_null($trace) ? new \null_progress_trace() : $trace;
        $this->service = $service;
        $this->settings = is_null($settings) ? modul_description::get_settings() : $settings;
    }

    /**
     * Tells whether a call ran into a fault meaning the webservice itself is unavailable.
     *
     * The adhoc import uses this to fail on purpose, so the task system retries later
     * instead of leaving a new course without its description.
     *
     * @return bool true if the webservice turned out to be unavailable
     */
    public function is_service_unavailable(): bool {
        return $this->serviceunavailable;
    }

    /**
     * Tells whether a soap fault only says that this event has no module description.
     *
     * @param string|null $message the fault message of the call
     * @return bool true if the fault is an answer and not a failure
     */
    public static function is_nodescription_fault($message): bool {
        $message = \core_text::strtolower((string)$message);
        foreach (self::NODESCRIPTION_MARKS as $mark) {
            if (strpos($message, $mark) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the webservice wrapper, building it on first use.
     *
     * @return \local_evento_evento_service the wrapper
     */
    protected function get_service() {
        if (is_null($this->service)) {
            $this->service = new \local_evento_evento_service();
        }

        return $this->service;
    }

    /**
     * Synchronises a batch of courses.
     *
     * @param int|null $limit the batch size, the configured one by default
     * @return \stdClass counters of the run, keyed by action
     */
    public function sync_courses($limit = null): \stdClass {
        $summary = new \stdClass();
        $summary->processed = 0;
        $summary->actions = array();
        $summary->stopped = false;

        if (!$this->settings->enabled) {
            $this->trace->output('The module description import is not enabled');
            return $summary;
        }

        \core_php_time_limit::raise();

        $courses = modul_description::get_sync_candidates($this->settings, null, $limit);
        $this->trace->output('Synchronising module descriptions of ' . count($courses) . ' courses...');

        foreach ($courses as $course) {
            $result = $this->sync_course($course);
            $summary->processed++;
            $summary->actions[$result->action] = ($summary->actions[$result->action] ?? 0) + 1;

            if ($this->serviceunavailable) {
                $this->trace->output('...the evento webservice is unavailable, stopping this run');
                $summary->stopped = true;
                break;
            }
        }

        $this->trace->output('...finished, ' . $summary->processed . ' courses processed');

        return $summary;
    }

    /**
     * Synchronises the module description of one course.
     *
     * @param \stdClass|int $courseorid the course or its id
     * @return \stdClass object with the properties action, reason and cmid
     */
    public function sync_course($courseorid): \stdClass {
        global $DB;

        $course = is_object($courseorid) ? $courseorid : $DB->get_record('course', array('id' => $courseorid),
            '*', MUST_EXIST);

        $anlassnummer = modul_description::resolve_anlassnummer($course);
        if (is_null($anlassnummer)) {
            $this->note_check($course, '', null, 'the course carries no evento event number',
                modul_description::ACTION_SKIP_NODESCRIPTION);

            return $this->finish($course, '', modul_description::ACTION_SKIP_NODESCRIPTION,
                'the course carries no evento event number', null);
        }

        $record = modul_description::get_link_record($course->id);
        $pages = modul_description::find_managed_pages($course->id, $this->settings->cmidnumber);
        if (count($pages) > 1) {
            // Typically a page carried over by a course template next to the one this plugin wrote.
            $this->trace->output('  WARNING: ' . count($pages) . ' pages carry the marker, using the first one');
        }
        $cm = empty($pages) ? null : reset($pages);
        $currenthash = is_null($cm) ? null : modul_description::content_hash(modul_description::get_page_content($cm));

        try {
            $answer = $this->get_service()->get_modulbeschreibung_by_number($anlassnummer);
        } catch (\local_evento_service_exception $ex) {
            // The faultstring names the real cause, getMessage() is the localised wrapper.
            $message = $ex->faultstring ?? $ex->getMessage();
            $cmid = is_null($cm) ? null : (int)$cm->id;

            if (self::is_nodescription_fault($message)) {
                // An answer and not a failure. Neither the course nor the run may be held
                // back for it, most modules simply carry no description in evento.
                $this->note_check($course, $anlassnummer, $cmid,
                    'evento knows no description for this event number',
                    modul_description::ACTION_SKIP_NODESCRIPTION);

                return $this->finish($course, $anlassnummer, modul_description::ACTION_SKIP_NODESCRIPTION,
                    'evento knows no description for this event number', $cmid);
            }

            if (in_array((string)$ex->faultcode, self::STOP_FAULTCODES, true)) {
                $this->serviceunavailable = true;
            }
            // The fault code is kept, it is the only way to tell afterwards why a fault was
            // taken for a dead service.
            $this->store_failure($course, $anlassnummer, $cm,
                '[' . ($ex->faultcode ?? '-') . '] ' . $message);

            return $this->finish($course, $anlassnummer, modul_description::ACTION_SKIP_NODESCRIPTION,
                'the webservice call failed: ' . $message, $cmid, false);
        }

        $normalized = \local_evento_evento_service::normalize_modulbeschreibung($answer);
        $content = is_null($normalized) ? '' : modul_description::clean_content($normalized->mbtext);
        $newhash = modul_description::content_hash($content);

        // Neither the description line nor the name is part of the content and therefore
        // neither is covered by the hash. Both are compared separately, so that a page whose
        // line went stale or which was renamed by hand is brought back.
        $newintro = modul_description::build_intro($normalized);
        $newname = $this->settings->onrename === EVENTOCOURSECREATION_MB_ONRENAME_RESET
            ? $this->settings->pagename : null;
        $wanted = modul_description::page_state($newintro, $newname);
        $current = is_null($cm)
            ? null
            : modul_description::page_state(modul_description::get_page_intro($cm), $cm->name);

        $decision = modul_description::decide($record, $normalized, $newhash, $currenthash, $this->settings,
            null, $current, $wanted);
        $cmid = is_null($cm) ? null : (int)$cm->id;

        switch ($decision->action) {
            case modul_description::ACTION_CREATE:
                if (modul_description::had_a_page($record)
                        && $this->settings->ondelete !== EVENTOCOURSECREATION_MB_ONDELETE_RECREATE) {
                    // There used to be a page, so it was deleted by hand. Respect the configuration.
                    return $this->handle_deleted_page($course, $anlassnummer);
                }
                $cm = modul_description_page::create($course, $this->settings->pagename, $content,
                    $this->settings, $newintro);
                $cmid = (int)$cm->id;
                $this->after_write($course, $cm, $normalized, $newhash, $anlassnummer);
                break;

            case modul_description::ACTION_UPDATE:
                // A null name keeps the one the page carries, which is what the setting to
                // leave a name given by hand alone amounts to.
                $cm = modul_description_page::update($course, $cm, $newname, $content, $this->settings, $newintro);
                $cmid = (int)$cm->id;
                $this->after_write($course, $cm, $normalized, $newhash, $anlassnummer);
                break;

            case modul_description::ACTION_METADATA:
                // The page stays untouched, only the evento state of the record moves on.
                $this->store_record($course, $anlassnummer, $cmid, $normalized, $record->contenthash,
                    modul_description::ACTION_METADATA);
                break;

            case modul_description::ACTION_NONE:
                $this->note_check($course, $anlassnummer, $cmid);
                break;

            default:
                // Every skip still notes that the course has been looked at, otherwise a course
                // evento knows nothing about would sort to the front of every single batch.
                $this->note_check($course, $anlassnummer, $cmid, $decision->reason, $decision->action);
                break;
        }

        // Deliberately outside of the switch. The protection has to be checked whenever the
        // page is there, not only when something was written to it. A role which gained the
        // right to edit activities after the last write would otherwise never be taken care
        // of, because a description that does not change is never written again.
        if (!is_null($cm)) {
            $written = modul_description_page::protect($cm);
            if (!empty($written)) {
                $this->trace->output('  protected the page against ' . implode(', ', $written));
            }
        }

        return $this->finish($course, $anlassnummer, $decision->action, $decision->reason, $cmid);
    }

    /**
     * Runs everything that has to happen after the page has been written.
     *
     * @param \stdClass $course the course record
     * @param \cm_info $cm the course module of the page
     * @param \stdClass $normalized the normalized webservice answer
     * @param string $contenthash the hash of the content just written
     * @param string $anlassnummer the evento event number
     * @return void
     */
    protected function after_write(\stdClass $course, \cm_info $cm, \stdClass $normalized, $contenthash, $anlassnummer) {
        // Reload, the module info of a freshly written module may be stale.
        $cm = get_fast_modinfo($course->id)->get_cm($cm->id);
        modul_description_page::move_to_top($course, $cm);

        $this->store_record($course, $anlassnummer, (int)$cm->id, $normalized, $contenthash,
            modul_description::ACTION_UPDATE);
    }

    /**
     * Writes the link record of a course.
     *
     * @param \stdClass $course the course record
     * @param string $anlassnummer the evento event number
     * @param int|null $cmid the course module of the page
     * @param \stdClass $normalized the normalized webservice answer
     * @param string|null $contenthash the hash of the content the page holds
     * @param string $action the action that led here, only used for the log
     * @return void
     */
    protected function store_record(\stdClass $course, $anlassnummer, $cmid, \stdClass $normalized, $contenthash, $action) {
        global $DB;

        $now = time();
        $record = modul_description::get_link_record($course->id);
        $isnew = is_null($record);

        $record = $isnew ? new \stdClass() : $record;
        $record->courseid = $course->id;
        $record->cmid = $cmid;
        $record->anlassnummer = $anlassnummer;
        $record->idmb = $normalized->idmb;
        $record->idstatus = $normalized->idstatus;
        $record->mbversionscaled = $normalized->mbversionscaled;
        $record->mbgueltigab = $normalized->mbgueltigab;
        $record->contenthash = $contenthash;
        $record->pagename = $this->settings->pagename;
        $record->status = EVENTOCOURSECREATION_MB_STATUS_OK;
        $record->errorcount = 0;
        $record->lasterror = null;
        $record->timechecked = $now;
        if ($action !== modul_description::ACTION_METADATA) {
            $record->timemodified = $now;
        }

        if ($isnew) {
            $record->timecreated = $now;
            $record->timemodified = $now;
            $DB->insert_record('eventocoursecreation_page', $record);
        } else {
            $DB->update_record('eventocoursecreation_page', $record);
        }
    }

    /**
     * Notes that a course has been looked at, creating the link record if there is none.
     *
     * The record has to exist even when nothing was written. The candidate query orders by
     * timechecked, and a course without a record counts as never checked, so it would come
     * first in every batch and keep the other courses from ever being reached.
     *
     * @param \stdClass $course the course record
     * @param string $anlassnummer the evento event number
     * @param int|null $cmid the course module of the page, null if the course has none
     * @param string|null $note why nothing was written, null if there was nothing to write
     * @param string $action the action that led here, it decides the state to store
     * @return void
     */
    protected function note_check(\stdClass $course, $anlassnummer, $cmid, $note = null,
            $action = modul_description::ACTION_NONE) {
        global $DB;

        $now = time();
        $record = modul_description::get_link_record($course->id);
        $isnew = is_null($record);
        if (!$isnew && (int)$record->status === EVENTOCOURSECREATION_MB_STATUS_DISABLED) {
            // The course was excluded on purpose, only note that it was looked at.
            $record->timechecked = $now;
            $DB->update_record('eventocoursecreation_page', $record);
            return;
        }

        $record = $isnew ? new \stdClass() : $record;
        $record->courseid = $course->id;
        $record->anlassnummer = $anlassnummer;
        $record->cmid = $cmid;
        $record->status = modul_description::status_for_action($action, $cmid);
        $record->errorcount = 0;
        $record->lasterror = is_null($note) ? null : \core_text::substr($note, 0, 1000);
        $record->timechecked = $now;

        if ($isnew) {
            $record->timecreated = $now;
            $record->timemodified = 0;
            $DB->insert_record('eventocoursecreation_page', $record);
        } else {
            $DB->update_record('eventocoursecreation_page', $record);
        }
    }

    /**
     * Reacts to a page a teacher or an administrator deleted from the course.
     *
     * @param \stdClass $course the course record
     * @param string $anlassnummer the evento event number
     * @return \stdClass the outcome of the synchronisation
     */
    protected function handle_deleted_page(\stdClass $course, $anlassnummer): \stdClass {
        global $DB;

        if ($this->settings->ondelete === EVENTOCOURSECREATION_MB_ONDELETE_DISABLE) {
            $record = modul_description::get_link_record($course->id);
            $record->cmid = null;
            $record->status = EVENTOCOURSECREATION_MB_STATUS_DISABLED;
            $record->lasterror = 'the page was deleted, this course is excluded from now on';
            $record->timechecked = time();
            $DB->update_record('eventocoursecreation_page', $record);

            return $this->finish($course, $anlassnummer, modul_description::ACTION_SKIP_DELETED,
                'the page was deleted, this course is excluded from now on', null);
        }

        $this->note_check($course, $anlassnummer, null, 'the page was deleted, it is not created again',
            modul_description::ACTION_SKIP_DELETED);

        return $this->finish($course, $anlassnummer, modul_description::ACTION_SKIP_DELETED,
            'the page was deleted, it is not created again', null);
    }

    /**
     * Notes a failed synchronisation so the course is held back for a while.
     *
     * @param \stdClass $course the course record
     * @param string $anlassnummer the evento event number
     * @param \cm_info|null $cm the course module of the page
     * @param string $message the error message
     * @return void
     */
    protected function store_failure(\stdClass $course, $anlassnummer, $cm, $message) {
        global $DB;

        $now = time();
        $record = modul_description::get_link_record($course->id);
        $isnew = is_null($record);

        $record = $isnew ? new \stdClass() : $record;
        $record->courseid = $course->id;
        $record->anlassnummer = $anlassnummer;
        $record->status = EVENTOCOURSECREATION_MB_STATUS_ERROR;
        $record->errorcount = $isnew ? 1 : ((int)$record->errorcount + 1);
        $record->lasterror = \core_text::substr($message, 0, 1000);
        $record->timechecked = $now;

        if ($isnew) {
            $record->cmid = is_null($cm) ? null : (int)$cm->id;
            $record->timecreated = $now;
            $record->timemodified = 0;
            $DB->insert_record('eventocoursecreation_page', $record);
        } else {
            $DB->update_record('eventocoursecreation_page', $record);
        }
    }

    /**
     * Writes the outcome to the trace and to the log, and returns it.
     *
     * @param \stdClass $course the course record
     * @param string $anlassnummer the evento event number
     * @param string $action the decided action
     * @param string $reason the explanation of the decision
     * @param int|null $cmid the course module of the page
     * @param bool $triggerevent whether the event is fired, off for a failed webservice call
     * @return \stdClass object with the properties action, reason and cmid
     */
    protected function finish(\stdClass $course, $anlassnummer, $action, $reason, $cmid, $triggerevent = true) {
        $this->trace->output("{$course->shortname} [{$anlassnummer}]: {$action}, {$reason}");

        if ($triggerevent) {
            modul_description_synced::create(array(
                'context' => \context_course::instance($course->id),
                'courseid' => $course->id,
                'other' => array(
                    'action' => $action,
                    'reason' => $reason,
                    'anlassnummer' => $anlassnummer,
                    'cmid' => $cmid,
                ),
            ))->trigger();
        }

        $result = new \stdClass();
        $result->action = $action;
        $result->reason = $reason;
        $result->cmid = $cmid;

        return $result;
    }
}
