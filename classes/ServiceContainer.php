<?php

namespace local_eventocoursecreation;

use local_eventocoursecreation\api\EventoApiCache;
use local_eventocoursecreation\api\SmartEventFetcher;
use local_eventocoursecreation\api\ParallelEventFetcher;
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
     * @param progress_trace|null $trace Optional trace (defaults to null_progress_trace)
     */
    public function __construct(progress_trace $trace = null)
    {
        $this->trace = $trace ?? new \null_progress_trace();
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
                $this->getTemplateManager(),
                $this->getApiCache()
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
    public function getLogger(): EventoLogger
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
    public function getEventoService(): \local_evento_evento_service
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

    /**
     * Gets the EventoApiCache instance
     *
     * @return EventoApiCache
     */
    public function getApiCache(): EventoApiCache
    {
        if (!isset($this->services[EventoApiCache::class])) {
            $this->services[EventoApiCache::class] = new EventoApiCache();
        }
        return $this->services[EventoApiCache::class];
    }
    
    /**
     * Gets the SmartEventFetcher instance
     * 
     * @param array $config Optional configuration overrides
     * @return SmartEventFetcher
     */
    public function getSmartEventFetcher(array $config = []): SmartEventFetcher
    {
        // Create a new instance each time with custom config
        return new SmartEventFetcher(
            $this->getEventoService(),
            $this->getApiCache(),
            $this->getLogger(),
            $config
        );
    }
    
    /**
     * Gets the ParallelEventFetcher instance
     * 
     * @param array $config Optional configuration overrides
     * @return ParallelEventFetcher
     */
    public function getParallelEventFetcher(array $config = []): ParallelEventFetcher
    {
        // Create a new instance each time with custom config
        return new ParallelEventFetcher(
            $this->getApiCache(),
            $config,
            $this->getLogger()
        );
    }
    
    /**
     * Register a service instance
     * 
     * @param string $class Class name
     * @param mixed $instance Service instance
     * @return self For method chaining
     */
    public function registerService(string $class, $instance): self
    {
        $this->services[$class] = $instance;
        return $this;
    }
    
    /**
     * Get a service by class name
     * 
     * @param string $class Class name
     * @return mixed|null The service instance or null if not found
     */
    public function getService(string $class)
    {
        return $this->services[$class] ?? null;
    }
}