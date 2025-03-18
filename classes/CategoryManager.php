<?php

namespace local_eventocoursecreation;

use Exception;
use moodle_database;
use stdClass;
use core_course_category;

/**
 * Handles category management and hierarchy
 */
class CategoryManager
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
     * @var EventoCache The cache instance
     */
    private EventoCache $cache;

    /**
     * Constructor
     */
    public function __construct(moodle_database $db, EventoLogger $logger, EventoCache $cache)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * Creates the minimum necessary category structure based on Veranstalter data
     *
     * @param array    $veranstalterList List of Veranstalter objects from Evento
     * @param callable $eventChecker     Callback to check if events need courses
     * @return array Map of Veranstalter IDs to category IDs
     */
    public function createMinimalHierarchy(array $veranstalterList, callable $eventChecker): array
    {
        $required = [];
        $veranstalterMap = [];

        // Build lookup map
        foreach ($veranstalterList as $ver) {
            $veranstalterMap[$ver->IDBenutzer] = $ver;
        }

        // Identify required categories
        foreach ($veranstalterList as $ver) {
            if ($eventChecker($ver)) {
                $required[$ver->IDBenutzer] = $ver;
                
                // Mark parents as required
                $current = $ver;
                while (!empty($current->OE) && isset($veranstalterMap[$current->OE])) {
                    $parentId = $current->OE;
                    if (!isset($required[$parentId])) {
                        $required[$parentId] = $veranstalterMap[$parentId];
                    }
                    $current = $veranstalterMap[$parentId];
                }
            }
        }

        return $this->createCategories($required);
    }

    /**
     * Creates categories based on requirements
     *
     * @param array $required
     * @return array Map of Veranstalter IDs to category IDs
     * @throws CategoryCreationException
     */
    private function createCategories(array $required): array
    {
        $categoryMap = [];

        // Process root categories first
        foreach ($required as $id => $ver) {
            if (empty($ver->OE)) {
                $categoryMap[$id] = $this->ensureCategoryExists($ver, 0);
            }
        }

        // Process remaining categories in hierarchy order
        $remaining = array_filter($required, fn($ver) => !empty($ver->OE));
        
        while (!empty($remaining)) {
            foreach ($remaining as $id => $ver) {
                if (isset($categoryMap[$ver->OE])) {
                    $categoryMap[$id] = $this->ensureCategoryExists($ver, $categoryMap[$ver->OE]);
                    unset($remaining[$id]);
                }
            }
        }

        return $categoryMap;
    }

    /**
     * Creates or gets existing category
     *
     * @param stdClass $veranstalter
     * @param int      $parentId
     * @return int Category ID
     * @throws CategoryCreationException
     */
    private function ensureCategoryExists(stdClass $veranstalter, int $parentId): int 
    {
        $cacheKey = "category_veranstalter_{$veranstalter->IDBenutzer}";

        // Check cache
        $cachedId = $this->cache->get($cacheKey);
        if ($cachedId !== false) {
            return $cachedId;
        }

        try {
            // Check database
            $existing = $this->db->get_record('course_categories', [
                'idnumber' => $veranstalter->IDBenutzer
            ]);

            if ($existing) {
                // Get existing category through our wrapper
                $category = EventoCourseCategory::get($existing->id);
                $categoryId = $category->getId();

                // Verify settings exist for existing category
                $settings = EventoCategorySettings::getForCategory($categoryId);
                if (!$settings) {
                    $this->logger->info("Creating missing settings for existing category", [
                        'categoryId' => $categoryId,
                        'veranstalterId' => $veranstalter->IDBenutzer
                    ]);
                    
                    try {
                        $settings = EventoCategorySettings::createForCategory(
                            $categoryId,
                            EventoConfiguration::getInstance()->toArray()
                        );
                        
                        $this->logger->info("Created settings for existing category", [
                            'categoryId' => $categoryId,
                            'settingsId' => $settings->getId()
                        ]);
                    } catch (Exception $e) {
                        $this->logger->error("Failed to create settings for existing category", [
                            'categoryId' => $categoryId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            } else {
                // Create new category using Moodle's core API first
                $data = [
                    'name' => $veranstalter->benutzerName,
                    'idnumber' => $veranstalter->IDBenutzer,
                    'parent' => $parentId,
                    'visible' => 1
                ];
                
                $this->logger->info("Creating new category", [
                    'name' => $data['name'],
                    'idnumber' => $data['idnumber'],
                    'parent' => $data['parent']
                ]);
                
                // Create the base category
                $baseCategory = core_course_category::create($data);
                
                // Create default Evento settings
                $this->logger->info("Creating settings for new category", [
                    'categoryId' => $baseCategory->id,
                    'veranstalterId' => $veranstalter->IDBenutzer
                ]);
                
                try {
                    $settings = EventoCategorySettings::createForCategory(
                        $baseCategory->id,
                        EventoConfiguration::getInstance()->toArray()
                    );
                    
                    $this->logger->info("Created category settings", [
                        'categoryId' => $baseCategory->id,
                        'settingsId' => $settings->getId()
                    ]);
                    
                    // Verify settings were created
                    $verifySettings = EventoCategorySettings::getForCategory($baseCategory->id);
                    if (!$verifySettings) {
                        $this->logger->error("Settings not found after creation", [
                            'categoryId' => $baseCategory->id
                        ]);
                    }
                } catch (Exception $e) {
                    $this->logger->error("Failed to create category settings", [
                        'categoryId' => $baseCategory->id,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Now we can get our wrapped version
                $category = EventoCourseCategory::get($baseCategory->id);
                $categoryId = $category->getId();

                $this->logger->info("Created category", [
                    'name' => $data['name'],
                    'id' => $categoryId
                ]);
            }

            // Cache the result
            $this->cache->set($cacheKey, $categoryId);

            return $categoryId;
        } catch (Exception $e) {
            $this->logger->error("Failed to create category", [
                'error' => $e->getMessage(),
                'veranstalter' => $veranstalter->IDBenutzer
            ]);
            throw new CategoryCreationException(
                "Failed to create category: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Gets or creates period subcategory
     *
     * @param stdClass $event
     * @param EventoCourseCategory $parentCategory
     * @param EventoConfiguration $config
     * @param previewMode $bool
     * @return EventoCourseCategory
     */
    public function getPeriodSubcategory(
        stdClass $event,
        EventoCourseCategory $parentCategory,
        EventoConfiguration $config,
        bool $previewMode = false
    ): EventoCourseCategory {
        // Early return if categorization is disabled or missing date
        if (empty($event->anlassDatumVon)) {
            return $parentCategory;
        }

        // Check category-specific settings first
        $categorySettings = $parentCategory->getEventoSettings();
        
        // If settings are null or subcategory organization is set to 0 (none),
        // return the parent category directly
        if ($categorySettings === null || $categorySettings->getSubCatOrganization() === 0) {
            return $parentCategory;
        }

        // For options 1 and 2, create period subcategories
        $periodName = $this->determinePeriodName($event, $categorySettings);

        // Check existing subcategories
        foreach ($parentCategory->getEventoSubcategories() as $subcategory) {
            if ($subcategory->getName() === $periodName) {
                return $subcategory;
            }
        }

        // If in preview mode, return the parent category with info in logs
        if ($previewMode) {
            $this->logger->info("Preview mode: Would create period subcategory", [
                'period' => $periodName,
                'parent' => $parentCategory->getId()
            ]);
            return $parentCategory;
        }

        // Create new subcategory
        try {
            $periodIdNumber = $parentCategory->getIdNumber() . '_' . $periodName;
            return $parentCategory->createTermSubcategory($periodName, $periodIdNumber);
        } catch (Exception $e) {
            $this->logger->error("Failed to create period subcategory", [
                'error' => $e->getMessage(),
                'period' => $periodName
            ]);
            return $parentCategory;
        }
    }

    /**
     * Determines period name based on event date and settings
     *
     * @param stdClass $event
     * @param EventoCategorySettings $settings
     * @return string
     */
    private function determinePeriodName(stdClass $event, EventoCategorySettings $settings): string
    {
        $startDate = strtotime($event->anlassDatumVon);
        
        // Use the same logic as in CourseNaming::getPeriod
        switch ($settings->getSubCatOrganization()) {
            case 0:
                return date('Ymd', $startDate);
            case 1:
                $month = (int)date('n', $startDate);
                $year = date('y', $startDate);
                return ($month >= 8) ? "HS{$year}" : "FS{$year}";
            case 2:
                return date('Y', $startDate);
            default:
                return date('Ymd', $startDate);
        }
    }
}