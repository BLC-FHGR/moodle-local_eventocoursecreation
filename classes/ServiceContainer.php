<?php

namespace local_eventocoursecreation;

use progress_trace;

/**
 * Dependency injection container
 */
class ServiceContainer
{
    /**
     * @var array The service instances
     */
    private array $services = [];

    /**
     * @var progress_trace The trace object
     */
    private progress_trace $trace;

    /**
     * Constructor
     *
     * @param progress_trace $trace
     */
    public function __construct(progress_trace $trace)
    {
        $this->trace = $trace;
    }

    /**
     * Gets the CourseCreationService instance
     *
     * @return CourseCreationService
     */
    public function getCourseCreationService(): CourseCreationService
    {
        if (!isset($this->services[CourseCreationService::class])) {
            $this->services[CourseCreationService::class] = new CourseCreationService(
                $this->getConfiguration(),
                $this->getCourseRepository(),
                $this->getEnrollmentManager(),
                $this->getLogger(),
                $this->getEventoService(),
                $this->getCategoryManager(),
                $this->getTemplateManager()
            );
        }

        return $this->services[CourseCreationService::class];
    }

    /**
     * Gets the EventoConfiguration instance
     *
     * @return EventoConfiguration
     */
    private function getConfiguration(): EventoConfiguration
    {
        if (!isset($this->services[EventoConfiguration::class])) {
            $this->services[EventoConfiguration::class] = EventoConfiguration::getInstance();
        }
        return $this->services[EventoConfiguration::class];
    }

    /**
     * Gets the CourseRepository instance
     *
     * @return CourseRepository
     */
    private function getCourseRepository(): CourseRepository
    {
        if (!isset($this->services[CourseRepository::class])) {
            global $DB;
            $this->services[CourseRepository::class] = new CourseRepository(
                $DB,
                $this->getCache(),
                $this->getLogger()
            );
        }
        return $this->services[CourseRepository::class];
    }

    /**
     * Gets the TemplateManager instance
     *
     * @return TemplateManager
     */
    private function getTemplateManager(): TemplateManager
    {
        if (!isset($this->services[TemplateManager::class])) {
            $this->services[TemplateManager::class] = new TemplateManager(
                $this->getLogger(),
                $this->getCache()
            );
        }
        return $this->services[TemplateManager::class];
    }

    /**
     * Gets the EnrollmentManager instance
     *
     * @return EnrollmentManager
     */
    private function getEnrollmentManager(): EnrollmentManager
    {
        if (!isset($this->services[EnrollmentManager::class])) {
            global $DB;
            $this->services[EnrollmentManager::class] = new EnrollmentManager(
                $DB,
                $this->getLogger()
            );
        }
        return $this->services[EnrollmentManager::class];
    }

    /**
     * Gets the EventoLogger instance
     *
     * @return EventoLogger
     */
    private function getLogger(): EventoLogger
    {
        if (!isset($this->services[EventoLogger::class])) {
            $this->services[EventoLogger::class] = new EventoLogger($this->trace);
        }
        return $this->services[EventoLogger::class];
    }

    /**
     * Gets the EventoCache instance
     *
     * @return EventoCache
     */
    private function getCache(): EventoCache
    {
        if (!isset($this->services[EventoCache::class])) {
            $this->services[EventoCache::class] = new EventoCache();
        }
        return $this->services[EventoCache::class];
    }

    /**
     * Gets the EventoService instance
     *
     * @return \local_evento_evento_service
     */
    private function getEventoService(): \local_evento_evento_service
    {
        if (!isset($this->services[\local_evento_evento_service::class])) {
            $this->services[\local_evento_evento_service::class] = new \local_evento_evento_service(
                null,
                null,
                $this->trace
            );
        }
        return $this->services[\local_evento_evento_service::class];
    }

    /**
     * Gets the CategoryManager instance
     *
     * @return CategoryManager
     */
    private function getCategoryManager(): CategoryManager
    {
        if (!isset($this->services[CategoryManager::class])) {
            global $DB;
            $this->services[CategoryManager::class] = new CategoryManager(
                $DB,
                $this->getLogger(),
                $this->getCache()
            );
        }
        return $this->services[CategoryManager::class];
    }
}
