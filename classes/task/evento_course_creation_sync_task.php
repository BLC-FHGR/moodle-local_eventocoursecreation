<?php

namespace local_eventocoursecreation\task;

use core\task\scheduled_task;
use Exception;
use local_eventocoursecreation\ValidationException;
use local_eventocoursecreation\EventoLogger;
use local_eventocoursecreation\ServiceContainer;
use local_eventocoursecreation\EventoConfiguration;
use text_progress_trace;

/**
 * Main task handler for scheduled synchronization
 */
class evento_course_creation_sync_task extends scheduled_task
{
    /**
     * Gets the name of the task
     *
     * @return string
     */
    public function get_name(): string
    {
        return get_string('taskname', 'local_eventocoursecreation');
    }

    /**
     * Executes the scheduled task
     *
     * @throws Exception
     */
    public function execute()
    {
        $trace = new text_progress_trace();
        $logger = new EventoLogger($trace);

        try {
            $container = new ServiceContainer($trace);
            $syncService = $container->getCourseCreationService();

            if (!EventoConfiguration::getInstance()->isPluginEnabled()) {
                $logger->info("Plugin is disabled");
                return;
            }

            $result = $syncService->synchronizeAll();

            if ($result === 0) {
                $logger->info("Synchronization completed successfully");
            } else {
                $logger->error("Synchronization failed", ['result' => $result]);
            }
        } catch (ValidationException $e) {
            $logger->error("Validation failed", ['error' => $e->getMessage()]);
        } catch (Exception $e) {
            $logger->error("Task execution failed", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
