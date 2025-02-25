<?php

namespace local_eventocoursecreation;

use dml_exception;
use moodle_database;
use stdClass;

/**
 * Database implementation of course repository
 */
class CourseRepository implements CourseRepositoryInterface
{
    /**
     * @var moodle_database The Moodle database instance
     */
    private moodle_database $db;

    /**
     * @var EventoCache The cache instance
     */
    private EventoCache $cache;

    /**
     * @var EventoLogger The logger instance
     */
    private EventoLogger $logger;

    /**
     * Constructor
     *
     * @param moodle_database $db
     * @param EventoCache $cache
     * @param EventoLogger $logger
     */
    public function __construct(moodle_database $db, EventoCache $cache, EventoLogger $logger)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Finds a course by its Evento ID
     *
     * @param int $eventoId
     * @return stdClass|null
     */
    public function findByEventoId(int $eventoId): ?stdClass
    {
        $cacheKey = "course_evento_{$eventoId}";
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $course = $this->db->get_record('course', ['idnumber' => $eventoId]);

        if ($course) {
            $this->cache->set($cacheKey, $course);
        }

        return $course ?: null;
    }

    /**
     * Finds a course by its short name
     *
     * @param string $shortName
     * @return stdClass|null
     */
    public function findByShortName(string $shortName): ?stdClass
    {
        return $this->db->get_record('course', ['shortname' => $shortName]) ?: null;
    }

    /**
     * Saves a course to the database
     *
     * @param stdClass $course
     * @return stdClass
     * @throws CourseCreationException
     */
    public function save(stdClass $course): stdClass
    {
        try {
            if (empty($course->id)) {
                $course->id = $this->db->insert_record('course', $course);
            } else {
                $this->db->update_record('course', $course);
            }

            // Clear cache after saving.
            if (!empty($course->idnumber)) {
                $this->cache->delete("course_evento_{$course->idnumber}");
            }

            return $course;
        } catch (dml_exception $e) {
            $this->logger->error('Failed to save course', [
                'error' => $e->getMessage(),
                'course' => $course
            ]);
            throw new CourseCreationException('Failed to save course: ' . $e->getMessage(), 0, $e);
        }
    }
}
