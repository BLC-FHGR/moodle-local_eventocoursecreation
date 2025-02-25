<?php

namespace local_eventocoursecreation;

use Exception;
use moodle_database;
use stdClass;

/**
 * Handles enrollment operations
 */
class EnrollmentManager
{
    /**
     * @var moodle_database The Moodle database instance
     */
    private moodle_database $db;

    /**
     * @var EventoLogger The logger instance
     */
    private EventoLogger $logger;

    /**
     * Constructor
     *
     * @param moodle_database $db
     * @param EventoLogger $logger
     */
    public function __construct(moodle_database $db, EventoLogger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Creates an enrollment instance for a course and event
     *
     * @param stdClass $course
     * @param stdClass $event
     * @return bool
     */
    public function createEnrollmentInstance(stdClass $course, stdClass $event): bool
    {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/evento/lib.php');

        try {
            $enrol = enrol_get_plugin('evento');
            $fields = $enrol->get_instance_defaults();
            $fields['customint4'] = $event->idAnlass;
            $fields['customtext1'] = $event->anlassNummer;

            if (isset($event->anlass_Zusatz15) && $event->anlass_Zusatz15 !== $event->idAnlass) {
                $fields['name'] = 'Evento Parallelanlass';
            }

            if ($enrol->add_instance($course, $fields)) {
                $this->logger->info("Enrollment instance created", [
                    'courseId' => $course->id,
                    'eventoId' => $event->idAnlass
                ]);
                return true;
            }

            throw new Exception("Failed to add enrollment instance");
        } catch (Exception $e) {
            $this->logger->error("Failed to create enrollment instance", [
                'error' => $e->getMessage(),
                'courseId' => $course->id,
                'eventoId' => $event->idAnlass
            ]);
            return false;
        }
    }

    /**
     * Checks if the course has an Evento enrollment instance
     *
     * @param int $courseId
     * @param int $eventoId
     * @return bool
     */
    public function hasEventoEnrollment(int $courseId, int $eventoId): bool
    {
        return $this->db->record_exists('enrol', [
            'courseid' => $courseId,
            'customint4' => $eventoId,
            'enrol' => 'evento'
        ]);
    }
}
