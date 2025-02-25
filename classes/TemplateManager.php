<?php

namespace local_eventocoursecreation;

use backup;
use Exception;
use restore_controller;
use stdClass;

/**
 * Handles course template operations
 */
class TemplateManager
{
    /**
     * @var EventoLogger The logger instance
     */
    private EventoLogger $logger;

    /**
     * @var EventoCache The cache instance
     */
    private EventoCache $cache;

    /**
     * Constructor
     *
     * @param EventoLogger $logger
     * @param EventoCache $cache
     */
    public function __construct(EventoLogger $logger, EventoCache $cache)
    {
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * Restores a course template
     *
     * @param int $courseId
     * @param int $templateId
     * @return bool
     */
    public function restoreTemplate(int $courseId, int $templateId): bool
    {
        global $USER;

        try {
            $backupdir = $this->getTemplateBackupDir($templateId);
            if (!$backupdir) {
                throw new Exception("Could not get template backup directory");
            }

            $tempdir = make_backup_temp_directory($backupdir);
            $rc = new restore_controller(
                $backupdir,
                $courseId,
                backup::INTERACTIVE_NO,
                backup::MODE_GENERAL,
                $USER->id,
                backup::TARGET_EXISTING_ADDING
            );

            $this->configureRestore($rc);

            if ($rc->execute_precheck()) {
                $rc->execute_plan();
                $this->logger->info("Template restored successfully", [
                    'courseId' => $courseId,
                    'templateId' => $templateId
                ]);
                return true;
            }

            throw new Exception("Restore precheck failed");
        } catch (Exception $e) {
            $this->logger->error("Template restore failed", [
                'error' => $e->getMessage(),
                'courseId' => $courseId,
                'templateId' => $templateId
            ]);
            return false;
        }
    }

    /**
     * Configures the restore settings
     *
     * @param restore_controller $rc
     */
    private function configureRestore(restore_controller $rc): void
    {
        $rc->get_plan()->get_setting('users')->set_value(false);
        $rc->get_plan()->get_setting('user_files')->set_value(false);
        if ($rc->get_plan()->setting_exists('role_assignments')) {
            $rc->get_plan()->get_setting('role_assignments')->set_value(false);
        }
    }

    /**
     * Gets the backup directory of the template
     *
     * @param int $templateId
     * @return string|null
     */
    private function getTemplateBackupDir(int $templateId): ?string
    {
        $cacheKey = "template_backup_{$templateId}";
        return $this->cache->get($cacheKey) ?? $this->createTemplateBackup($templateId);
    }

    /**
     * Creates a backup of the template
     *
     * @param int $templateId
     * @return string|null
     */
    private function createTemplateBackup(int $templateId): ?string
    {
        // Template backup creation logic here
        // Returns backup directory path or null on failure
        return null;
    }
}
