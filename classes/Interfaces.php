<?php

namespace local_eventocoursecreation\interfaces;

use stdClass;
use local_eventocoursecreation\value_objects\CourseSettings;
use local_eventocoursecreation\EventoCourseCategory;

/**
 * Interface for the course creation service
 * 
 * This interface defines the contract for course creation operations while
 * maintaining compatibility with existing Moodle and Evento systems. It ensures
 * that implementations handle course creation according to institutional rules
 * and existing configuration constants.
 */
interface CourseCreationServiceInterface 
{
    /**
     * Status codes for synchronization results
     */
    public const STATUS_SUCCESS = 0;
    public const STATUS_ERROR = 1;
    public const STATUS_DISABLED = 2;

    /**
     * Synchronizes all courses based on Evento data
     * 
     * This method should handle the complete synchronization process including:
     * - Category structure creation
     * - Course creation based on Evento events
     * - Template application
     * - Enrollment setup
     *
     * @return int Status code indicating result
     */
    public function synchronizeAll(): int;

    /**
     * Creates a single course based on an Evento event
     * 
     * Course creation should respect the naming conventions defined in locallib.php:
     * - EVENTOCOURSECREATION_NAME_PH_EVENTO_NAME
     * - EVENTOCOURSECREATION_NAME_PH_EVENTO_ABR
     * - EVENTOCOURSECREATION_NAME_PH_PERIOD
     * etc.
     *
     * @param stdClass $event Evento event data
     * @param EventoCourseCategory $category Target category
     * @param bool $force Override validation checks
     * @return stdClass|null Created course or null if skipped
     */
    public function createCourse(
        stdClass $event, 
        EventoCourseCategory $category, 
        bool $force = false
    ): ?stdClass;
}

/**
 * Interface for Evento service integration
 * 
 * Defines the contract for interacting with the Evento system while maintaining
 * compatibility with existing term definitions and module numbering schemes.
 */
interface EventoServiceInterface 
{
    /**
     * Initializes the Evento connection
     *
     * @return bool Success status
     */
    public function init_call(): bool;

    /**
     * Gets all active Veranstalter from Evento
     * 
     * @return array List of Veranstalter objects
     */
    public function get_active_veranstalter(): array;

    /**
     * Gets events for a specific Veranstalter
     * 
     * This method should respect the term prefixes defined in locallib.php:
     * - EVENTOCOURSECREATION_SPRINGTERM_PREFIX
     * - EVENTOCOURSECREATION_AUTUMNTERM_PREFIX
     *
     * @param string $veranstalterId Veranstalter identifier
     * @return array List of event objects
     */
    public function get_events_by_veranstalter(string $veranstalterId): array;

    /**
     * Gets events filtered by current and next year
     * 
     * Should handle EMBA courses (EVENTOCOURSECREATION_EMBA_PREFIX) appropriately
     *
     * @param string $veranstalterId Veranstalter identifier
     * @return array Filtered event list
     */
    public function get_events_by_veranstalter_years(string $veranstalterId): array;

    /**
     * Checks if the service is properly configured
     *
     * @return bool Configuration status
     */
    public function isConfigured(): bool;
}

/**
 * Interface for course repository operations
 * 
 * Defines the contract for course storage operations while maintaining
 * compatibility with Moodle's course management system.
 */
interface CourseRepositoryInterface 
{
    /**
     * Saves a new course
     *
     * @param stdClass $course Course data
     * @return stdClass Saved course with ID
     */
    public function save(stdClass $course): stdClass;

    /**
     * Finds a course by its Evento ID
     *
     * @param int $eventoId Evento identifier
     * @return stdClass|null Course data or null if not found
     */
    public function findByEventoId(int $eventoId): ?stdClass;

    /**
     * Checks if a shortname is unique
     * 
     * @param string $shortname Course shortname
     * @return bool True if unique
     */
    public function isShortNameUnique(string $shortname): bool;
}

/**
 * Interface for template management
 * 
 * Defines the contract for course template operations.
 */
interface TemplateManagerInterface 
{
    /**
     * Restores a template to a new course
     *
     * @param int $courseId Target course ID
     * @param int $templateId Template course ID
     * @return bool Success status
     */
    public function restoreTemplate(int $courseId, int $templateId): bool;
}

/**
 * Interface for enrollment management
 * 
 * Defines the contract for handling course enrollments.
 */
interface EnrollmentManagerInterface 
{
    /**
     * Creates an enrollment instance for an event
     *
     * @param stdClass $course Course object
     * @param stdClass $event Evento event
     * @return bool Success status
     */
    public function createEnrollmentInstance(stdClass $course, stdClass $event): bool;
}