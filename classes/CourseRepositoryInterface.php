<?php

namespace local_eventocoursecreation;

use stdClass;

/**
 * Repository interface for course data access
 */
interface CourseRepositoryInterface
{
    /**
     * Finds a course by its Evento ID
     *
     * @param int $eventoId
     * @return stdClass|null
     */
    public function findByEventoId(int $eventoId): ?stdClass;

    /**
     * Finds a course by its short name
     *
     * @param string $shortName
     * @return stdClass|null
     */
    public function findByShortName(string $shortName): ?stdClass;

    /**
     * Saves a course to the database
     *
     * @param stdClass $course
     * @return stdClass
     */
    public function save(stdClass $course): stdClass;
}
