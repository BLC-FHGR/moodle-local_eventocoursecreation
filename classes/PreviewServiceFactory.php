<?php

namespace local_eventocoursecreation;

use progress_trace;

/**
 * Factory for CoursePreviewService
 */
class PreviewServiceFactory
{
    /**
     * Create a CoursePreviewService instance
     *
     * @param progress_trace|null $trace Optional trace object
     * @return CoursePreviewService
     */
    public static function create(?progress_trace $trace = null): CoursePreviewService
    {
        $trace = $trace ?? new \null_progress_trace();
        $container = new ServiceContainer($trace);
        
        // Get the CourseCreationService from the container
        $courseCreationService = $container->getCourseCreationService();
        
        return new CoursePreviewService(
            $container->getLogger(),
            $container->getEventoService(),
            $container->getService(CategoryManager::class) ?? new CategoryManager(
                $GLOBALS['DB'],
                $container->getLogger(),
                new EventoCache()
            ),
            $container->getService(CourseRepository::class) ?? new CourseRepository(
                $GLOBALS['DB'],
                new EventoCache(),
                $container->getLogger()
            ),
            EventoConfiguration::getInstance(),
            $courseCreationService,
            $trace
        );
    }
}