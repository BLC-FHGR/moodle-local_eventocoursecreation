<?php

namespace local_eventocoursecreation;

use DateTime;
use progress_trace;
use local_eventocoursecreation\api\EventoApiCache;
use local_eventocoursecreation\api\FastModeSynchronizer;
use local_eventocoursecreation\api\SmartEventFetcher;
use Exception;
use moodle_exception;
use local_evento_evento_service;
use stdClass;
use local_eventocoursecreation\exceptions\ValidationException;

/**
 * Main course creation service
 */
class CourseCreationService
{
    /**
     * @var EventoConfiguration
     */
    private EventoConfiguration $config;

    /**
     * @var CourseRepository
     */
    private CourseRepository $courseRepository;

    /**
     * @var EnrollmentManager
     */
    private EnrollmentManager $enrollmentManager;

    /**
     * @var EventoLogger
     */
    private EventoLogger $logger;

    /**
     * @var local_evento_evento_service
     */
    private local_evento_evento_service $eventoService;

    /**
     * @var CategoryManager
     */
    private CategoryManager $categoryManager;

    /**
     * @var TemplateManager
     */
    private TemplateManager $templateManager;

    /**
     * @var EventoApiCache
     */
    private EventoApiCache $apiCache;

    /**
     * @var SmartEventFetcher
     */
    private ?SmartEventFetcher $eventFetcher = null;

    private $filteredEventCount = 0;
    private $eventCreatedCount = 0;
    private $filteredEventReasons = [];
    
    /**
     * Constructor
     */
    public function __construct(
        EventoConfiguration $config,
        CourseRepository $courseRepository,
        EnrollmentManager $enrollmentManager,
        EventoLogger $logger,
        \local_evento_evento_service $eventoService,
        CategoryManager $categoryManager,
        TemplateManager $templateManager,
        EventoApiCache $apiCache
    ) {
        $this->config = $config;
        $this->courseRepository = $courseRepository;
        $this->enrollmentManager = $enrollmentManager;
        $this->logger = $logger;
        $this->eventoService = $eventoService;
        $this->categoryManager = $categoryManager;
        $this->templateManager = $templateManager;
        $this->apiCache = $apiCache;
    }
    
    /**
     * Initialize the event fetcher based on category settings
     * 
     * @param EventoCourseCategory $category The category to use settings from
     */
    private function initializeEventFetcher(EventoCourseCategory $category = null) {
        // Default configuration from global settings
        $fetcherConfig = [
            'batch_size' => (int)get_config('local_eventocoursecreation', 'batch_size') ?: 200,
            'min_batch_size' => (int)get_config('local_eventocoursecreation', 'min_batch_size') ?: 10,
            'max_batch_size' => (int)get_config('local_eventocoursecreation', 'max_batch_size') ?: 1000,
            'adaptive_batch_sizing' => (bool)get_config('local_eventocoursecreation', 'adaptive_batch_sizing'),
            'date_chunk_fallback' => (bool)get_config('local_eventocoursecreation', 'date_chunk_fallback'),
            'date_chunk_days' => (int)get_config('local_eventocoursecreation', 'date_chunk_days') ?: 90,
            'max_api_retries' => (int)get_config('local_eventocoursecreation', 'max_api_retries') ?: 3,
            'cache_ttl' => (int)get_config('local_eventocoursecreation', 'cache_ttl') ?: 3600,
            'enable_incremental' => get_config('local_eventocoursecreation', 'fetching_mode') === 'fast',
        ];
        
        // If category is provided, check for category-specific settings
        if ($category !== null) {
            $catSettings = \local_eventocoursecreation_setting::get($category->getId());
            
            if ($catSettings->override_global_fetching) {
                $this->logger->info("Using category-specific fetching settings for category {$category->getName()}");
                
                // Override mode if set
                if (!empty($catSettings->fetching_mode)) {
                    $fetcherConfig['enable_incremental'] = $catSettings->fetching_mode === 'fast';
                }
                
                // Override batch size if set
                if (!empty($catSettings->custom_batch_size)) {
                    $fetcherConfig['batch_size'] = (int)$catSettings->custom_batch_size;
                }
            }
        }
        
        // Create the event fetcher
        $this->eventFetcher = new SmartEventFetcher(
            $this->eventoService,
            $this->apiCache,
            $this->logger,
            $fetcherConfig
        );
        
        $this->logger->debug('Event fetcher initialized', [
            'mode' => get_config('local_eventocoursecreation', 'fetching_mode'),
            'batch_size' => $fetcherConfig['batch_size']
        ]);
    }

    /**
     * Synchronizes all courses based on Evento data
     *
     * @return int Status code (0: success, 1: error, 2: disabled/no data)
     */
    public function synchronizeAll(): int
    {
        try {
            if (!$this->config->isPluginEnabled()) {
                $this->logger->info("Plugin is disabled");
                return 2;
            }

            if (!$this->eventoService->init_call()) {
                $this->logger->error("Failed to initialize Evento connection");
                return 2;
            }

            // Get active Veranstalter list
            $veranstalterList = $this->eventoService->get_active_veranstalter();
            if (empty($veranstalterList)) {
                $this->logger->info("No active Veranstalter found");
                return 2;
            }

            // Get the global fetching mode
            $globalFetchingMode = get_config('local_eventocoursecreation', 'fetching_mode') ?: 'classic';
            $this->logger->info("Global fetching mode: {$globalFetchingMode}");

            // Create category hierarchy and get category mapping
            $categoryMap = $this->categoryManager->createMinimalHierarchy(
                $veranstalterList,
                function($ver) {
                    // This callback just checks if the Veranstalter has any eventsor is empty maybe it 
                    // would save time to just fetch one event in the valid time period to see if one exists.
                    return !empty($this->eventoService->get_events_by_veranstalter_years($ver->IDBenutzer));
                }
            );

            // Process events for each category
            foreach ($categoryMap as $veranstalterId => $categoryId) {
                try {
                    $category = EventoCourseCategory::get($categoryId);
                    
                    // If the course creation in the evento settings of the current course category is disabled, skip.
                    // If both autumn and spring creation are set to only execute on start date and it is neither of them, skip.

                    // Check if category has a specific mode override
                    $catSettings = \local_eventocoursecreation_setting::get($category->getId());
                    $fetchingMode = $catSettings->override_global_fetching && !empty($catSettings->fetching_mode)
                        ? $catSettings->fetching_mode
                        : $globalFetchingMode;
                    
                    $this->logger->info("Processing category {$category->getName()} with mode: {$fetchingMode}");
                    
                    // Initialize event fetcher with the appropriate settings
                    $this->initializeEventFetcher($category);
                    
                    // Process the category with the selected mode
                    switch ($fetchingMode) {
                        case 'fast':
                            $this->processCategoryWithFastMode($category, $veranstalterId);
                            break;
                        case 'smart':
                            $this->processCategoryWithSmartMode($category, $veranstalterId);
                            break;
                        case 'parallel':
                            if (PHP_SAPI === 'cli') {
                                $this->processCategoryWithParallelMode($category, $veranstalterId);
                            } else {
                                $this->logger->error("Parallel mode requires CLI. Falling back to smart mode.");
                                $this->processCategoryWithSmartMode($category, $veranstalterId);
                            }
                            break;
                        case 'classic':
                        default:
                            $this->processCategoryWithClassicMode($category, $veranstalterId);
                    }
                } catch (Exception $e) {
                    $this->logger->error("Failed to process category", [
                        'categoryId' => $categoryId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->logger->info("Synchronization completed successfully");
            $this->logger->info("Category processing summary", [
                'filtered_events' => $this->filteredEventCount,
                'filtered_reasons' => $this->filteredEventReasons,
                'created_events' => $this->eventCreatedCount
            ]);
            return 0;
        } catch (Exception $e) {
            $this->logger->error("Synchronization failed", [
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

     /**
     * Process a category with Classic Mode (original implementation)
     * 
     * @param EventoCourseCategory $category The category to process
     * @param string $veranstalterId The Veranstalter ID associated with the category
     */
    private function processCategoryWithClassicMode(EventoCourseCategory $category, string $veranstalterId): void
    {
        $this->logger->info("Using classic mode for category {$category->getName()}");
        
        $events = $this->eventoService->get_events_by_veranstalter_years($veranstalterId);
        
        if (empty($events)) {
            $this->logger->info("No events found for Veranstalter {$veranstalterId}");
            return;
        }
        
        $this->logger->info("Found " . count($events) . " events for processing");
        
        foreach ($events as $event) {
            try {
                // Get or create period subcategory if needed
                $targetCategory = $this->categoryManager->getPeriodSubcategory(
                    $event, 
                    $category,
                    $this->config
                );
                
                $this->createCourse($event, $targetCategory);
            } catch (Exception $e) {
                $this->logger->error("Failed to process event", [
                    'eventId' => $event->idAnlass,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Process a category with Classic Mode (original implementation)
     * 
     * @param EventoCourseCategory $category The category to process
     * @param string $veranstalterId The Veranstalter ID associated with the category
     */
    private function processCategoryWithFastMode(EventoCourseCategory $category, string $veranstalterId): void
    {
        $this->logger->info("Using fast mode for category {$category->getName()}");
        
        try {
             // Check if category and its subcategories have any evento courses
            global $DB;
            
            // Get all courses in this category and subcategories
            $sql = "SELECT COUNT(c.id) 
                    FROM {course} c
                    JOIN {course_categories} cc ON c.category = cc.id
                    WHERE (cc.id = :catid OR cc.parent = :parentid)
                    AND c.idnumber IS NOT NULL AND c.idnumber != ''";
                    
            $courseCount = $DB->count_records_sql($sql, ['catid' => $category->getId(), 'parentid' => $category->getId()]);

            if ($courseCount === 0) {
                $this->logger->info("Empty category detected, switching to smart mode for initial population");
                $this->processCategoryWithSmartMode($category, $veranstalterId);
                return;
            }

            // Use Improved Fast Mode Synchronizer
            $fastSync = new FastModeSynchronizer(
                $this->eventoService,
                $this->apiCache,
                $this->logger
            );

            $events = $fastSync->getNewEvents($veranstalterId, $category->getId());
            
            if (empty($events)) {
                $this->logger->info("No new events found for Veranstalter {$veranstalterId}");
                return;
            }
            
            $this->logger->info("Found " . count($events) . " events for processing");
            
            foreach ($events as $event) {
                try {
                    // Get or create period subcategory if needed
                    $targetCategory = $this->categoryManager->getPeriodSubcategory(
                        $event, 
                        $category,
                        $this->config
                    );
                    
                    $this->createCourse($event, $targetCategory);
                } catch (Exception $e) {
                    $this->logger->error("Failed to process event", [
                        'eventId' => $event->idAnlass,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Memory management
                unset($event);
                if (count($events) > 100 && rand(0, 100) < 5) {
                    gc_collect_cycles();
                }
            }
            
            // Clean up
            $events = null;
            gc_collect_cycles();
        } catch (Exception $e) {
            $this->logger->error("Fast mode failed for category {$category->getName()}", [
                'error' => $e->getMessage()
            ]);
            
            // Fall back to classic mode if fast mode fails
            $this->logger->info("Falling back to classic mode");
            $this->processCategoryWithClassicMode($category, $veranstalterId);
        }
    }

    /**
     * Process a category with Smart Mode (adaptive batching)
     * 
     * @param EventoCourseCategory $category The category to process
     * @param string $veranstalterId The Veranstalter ID associated with the category
     */
    private function processCategoryWithSmartMode(EventoCourseCategory $category, string $veranstalterId): void
    {
        $this->logger->info("Using smart mode for category {$category->getName()}");
        
        try {
            // Use Smart Event Fetcher with date range
            $fromDate = new \DateTime(date('Y-01-01')); // Jan 1st of current year
            $toDate = new \DateTime((date('Y')+1).'-12-31'); // Dec 31st of next year
            
            $events = $this->eventFetcher->fetchAllEvents($veranstalterId, $fromDate, $toDate);
            
            if (empty($events)) {
                $this->logger->info("No events found for Veranstalter {$veranstalterId}");
                return;
            }
            
            $this->logger->info("Found " . count($events) . " events for processing");
            
            foreach ($events as $event) {
                try {
                    // Check if we can create this course BEFORE trying to create subcategory
                    if (!$this->canCreateCourse($event)) {
                        continue;
                    }
                    
                    // Only now try to get or create the subcategory
                    $targetCategory = $this->categoryManager->getPeriodSubcategory(
                        $event, 
                        $category,
                        $this->config
                    );
                    
                    $this->createCourse($event, $targetCategory);
                } catch (Exception $e) {
                    $this->logger->error("Failed to process event", [
                        'eventId' => $event->idAnlass,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Smart mode failed for category {$category->getName()}", [
                'error' => $e->getMessage()
            ]);
            
            // Fall back to classic mode if smart mode fails
            $this->logger->info("Falling back to classic mode");
            $this->processCategoryWithClassicMode($category, $veranstalterId);
        }
    }

    /**
     * Process a category with Parallel Mode (CLI-only)
     * 
     * @param EventoCourseCategory $category The category to process
     * @param string $veranstalterId The Veranstalter ID associated with the category
     */
    private function processCategoryWithParallelMode(EventoCourseCategory $category, string $veranstalterId): void
    {
        $this->logger->info("Using parallel mode for category {$category->getName()}");
        
        try {
            // Create parallel fetcher
            $parallelFetcher = new \local_eventocoursecreation\api\ParallelEventFetcher(
                $this->apiCache,
                [
                    'num_threads' => (int)get_config('local_eventocoursecreation', 'max_parallel_threads') ?: 2
                ]
            );
            
            // Process just this veranstalter
            $fromDate = new \DateTime(date('Y-01-01')); // Jan 1st of current year
            $toDate = new \DateTime((date('Y')+1).'-12-31'); // Dec 31st of next year
            
            $result = $parallelFetcher->fetchEventsForMultipleVeranstalter(
                [$veranstalterId],
                $fromDate,
                $toDate
            );
            
            if (empty($result[$veranstalterId])) {
                $this->logger->info("No events found for Veranstalter {$veranstalterId}");
                return;
            }
            
            $events = $result[$veranstalterId];
            $this->logger->info("Found " . count($events) . " events for processing");
            
            foreach ($events as $event) {
                try {
                    // Get or create period subcategory if needed
                    $targetCategory = $this->categoryManager->getPeriodSubcategory(
                        $event, 
                        $category,
                        $this->config
                    );
                    
                    $this->createCourse($event, $targetCategory);
                } catch (Exception $e) {
                    $this->logger->error("Failed to process event", [
                        'eventId' => $event->idAnlass,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } catch (Exception $e) {
            $this->logger->error("Parallel mode failed for category {$category->getName()}", [
                'error' => $e->getMessage()
            ]);
            
            // Fall back to smart mode if parallel mode fails
            $this->logger->info("Falling back to smart mode");
            $this->processCategoryWithSmartMode($category, $veranstalterId);
        }
    }

    /**
     * Creates a course based on an Evento event
     *
     * @param stdClass $event The Evento event data
     * @param EventoCourseCategory $category The category to create the course in
     * @param bool $force Whether to force creation regardless of timing rules
     * @return stdClass|null The created course or null if creation failed/skipped
     * @throws CourseCreationException
     */
    public function createCourse(
        stdClass $event, 
        EventoCourseCategory $category, 
        bool $force = false
    ): ?stdClass {
        try {
            try {
                $this->validateEvent($event);
            } catch (ValidationException $e) {
                $this->filteredEventCount++;
                $reason = $e->getMessage();
                if (!isset($this->filteredEventReasons[$reason])) {
                    $this->filteredEventReasons[$reason] = 0;
                }
                $this->filteredEventReasons[$reason]++;
                
                $this->logger->debug("Event filtered during validation", [
                    'reason' => $reason,
                    'eventoId' => $event->idAnlass
                ]);
                return null;
            }

            if (!$force && !$this->canCreateCourse($event)) {
                $this->logger->debug("Event not in creation period", ['eventoId' => $event->idAnlass]);
                return null;
            }

            // Important change: Get parent category if this is a semester subcategory
            $settingsCategory = $this->getSettingsCategory($event);
            
            $course = $this->buildCourseObject($event, $category, $settingsCategory);
            $savedCourse = $this->courseRepository->save($course);

            $this->eventCreatedCount++;

            $this->postCreationSetup($savedCourse, $event);

            $this->logger->info("Course created successfully", [
                'courseId' => $savedCourse->id,
                'eventoId' => $event->idAnlass,
                'category' => $category->getId()
            ]);

            return $savedCourse;
        } catch (Exception $e) {
            $this->logger->error("Course creation failed", [
                'error' => $e->getMessage(),
                'eventoId' => $event->idAnlass
            ]);
            throw new CourseCreationException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Validates the Evento event data
     *
     * @param stdClass $event
     * @throws ValidationException
     */
    private function validateEvent(stdClass $event): void
    {
        if (empty($event->anlassDatumVon) || empty($event->anlassDatumBis)) {
            throw new ValidationException("Event dates are missing");
        }

        if (strtotime($event->anlassDatumBis) < time()) {
            throw new ValidationException("Event end date is in the past");
        }

        if (isset($event->anlass_Zusatz15) && $event->anlass_Zusatz15 !== $event->idAnlass) {
            throw new ValidationException("Event is a sub-event");
        }

        if ($this->courseRepository->findByEventoId($event->idAnlass)) {
            throw new ValidationException("Course already exists for this event");
        }
    }

    /**
     * Checks if the course can be created based on current conditions
     *
     * @param stdClass $event
     * @return bool
     */
    private function canCreateCourse(stdClass $event): bool
    {
        if (!$this->config->isPluginEnabled()) {
            return false;
        }

        if (!$this->isInCreationPeriod($event)) {
            $this->logger->info("Not in creation period", ['eventoId' => $event->idAnlass]);
            return false;
        }

        return true;
    }

    /**
     * Builds the course object to be saved
     *
     * @param stdClass $event
     * @param EventoCourseCategory $category
     * @param EventoCourseCategory $settingsCategory
     * @return stdClass
     */
    private function buildCourseObject(
        stdClass $event, 
        EventoCourseCategory $category,
        EventoCourseCategory $settingsCategory
    ): stdClass {
        // Get settings from the appropriate category
        $categorySettings = $settingsCategory->getEventoSettings();
        
        // Fall back to global if no category settings
        if ($categorySettings === null) {
            $this->logger->warning("No category settings found, using global defaults", [
                'categoryId' => $settingsCategory->getId()
            ]);
            $courseSettings = $this->config->getCourseSettings();
        } else {
            // Create course settings adapter from category settings
            $courseSettings = new CourseSettingsAdapter($categorySettings);
        }

        // Create naming using appropriate settings
        $naming = new CourseNaming(
            $event,
            $categorySettings ?? $this->config->getCourseSettings(),
            $this->courseRepository
        );

        $course = new stdClass();
        $course->category = $category->getId();
        $course->idnumber = (string)$event->idAnlass;
        $course->fullname = $naming->getLongName();
        $course->shortname = $naming->getUniqueShortName();
        
        // Use category-specific settings where available
        $course->visible = $courseSettings->getCourseVisibility();
        $course->newsitems = $courseSettings->getNewsItemsNumber();
        $course->numsections = $courseSettings->getNumberOfSections();

        $this->setCourseDates($course, $event, $categorySettings);

        return $course;
    }

    /**
     * Sets the course dates
     *
     * @param stdClass $course
     * @param stdClass $event
     */
    private function setCourseDates(stdClass $course, stdClass $event, EventoCategorySettings $categorySettings): void
    {
        $course->startdate = strtotime($event->anlassDatumVon);
        $course->enddate = strtotime($event->anlassDatumBis);
    
        if ($categorySettings->getSetCustomCourseStartTime()) {
            $course->startdate = $categorySettings->getStartTimeCourse();
        }
    }

    /**
     * Fix course visibility issue after creation
     *
     * @param int $categoryid The category ID
     */
    private function fixCourseVisibility(int $categoryid): void {
        // Simply mark the category context as dirty to force rebuilds
        \context_coursecat::instance($categoryid)->mark_dirty();
        
        // For good measure, invalidate the coursecat cache
        \cache_helper::invalidate_by_definition('core', 'coursecat', array(), array($categoryid));
    }

    /**
     * Performs post-creation setup tasks
     *
     * @param stdClass $course
     * @param stdClass $event
     */
    private function postCreationSetup(stdClass $course, stdClass $event): void {
        $courseSettings = $this->config->getCourseSettings();

        if ($courseSettings->isTemplateCourseEnabled() && $courseSettings->getTemplateCourse()) {
            $this->templateManager->restoreTemplate($course->id, $courseSettings->getTemplateCourse());
        }

        $this->enrollmentManager->createEnrollmentInstance($course, $event);
        $this->handleSubEvents($course, $event);
        
        // Fix course counts to ensure visibility in category
        $this->fixCourseVisibility($course->category);
    }

    /**
     * Handles sub-events for the course
     *
     * @param stdClass $course
     * @param stdClass $event
     */
    private function handleSubEvents(stdClass $course, stdClass $event): void
    {
        try {
            // Find sub-events by checking anlass_Zusatz15 field
            $allEvents = $this->eventoService->get_events_by_veranstalter($event->anlassVeranstalter);
            $subEvents = array_filter($allEvents, function($subEvent) use ($event) {
                return isset($subEvent->anlass_Zusatz15)
                    && $subEvent->anlass_Zusatz15 === $event->idAnlass
                    && $subEvent->idAnlass !== $event->idAnlass;
            });

            foreach ($subEvents as $subEvent) {
                $this->enrollmentManager->createEnrollmentInstance($course, $subEvent);
            }
        } catch (Exception $e) {
            $this->logger->error("Failed to handle sub-events", [
                'error' => $e->getMessage(),
                'courseId' => $course->id,
                'eventoId' => $event->idAnlass
            ]);
        }
    }

    /**
     * Checks if the event is within the course creation period
     *
     * @param stdClass $event
     * @return bool
     */
    private function isInCreationPeriod(stdClass $event): bool
    {
        // Find the appropriate category for this event using existing methods
        $veranstalterId = !empty($event->anlassVeranstalter) ? $event->anlassVeranstalter : null;
        if (!$veranstalterId) {
            return true; // If we can't determine the category, default to allow
        }
        
        // Find categories with this Veranstalter ID
        $categories = \core_course_category::get_all(['idnumber' => $veranstalterId]);
        if (empty($categories)) {
            return true; // If no matching category, default to allow
        }
        
        // Get the first category with EventoCategorySettings
        $categorySettings = null;
        foreach ($categories as $cat) {
            $settings = EventoCategorySettings::getForCategory($cat->id);
            if ($settings) {
                $categorySettings = $settings;
                break;
            }
        }
        
        if (!$categorySettings) {
            return true; // No settings found, default to allow
        }
        
        // Get event start date and determine if spring or autumn term
        $eventStart = strtotime($event->anlassDatumVon);
        $eventMonth = (int)date('n', $eventStart);
        $isSpringTerm = ($eventMonth < 8); // Spring: Jan-Jul, Autumn: Aug-Dec
        
        // Now check the "execute only on start day" flags
        $todayDay = (int)date('j');
        $todayMonth = (int)date('n');
        
        if ($isSpringTerm) {
            // Spring term check
            if ($categorySettings->getExecOnlyOnStartTimeSpringTerm()) {
                // Only allow on the exact spring start day
                $springStartDay = $categorySettings->getStartTimeSpringTermDay();
                $springStartMonth = $categorySettings->getStartTimeSpringTermMonth();
                
                $this->logger->debug("Spring term restricted to start day check", [
                    'today' => "$todayDay/$todayMonth",
                    'springStart' => "$springStartDay/$springStartMonth",
                    'eventoId' => $event->idAnlass
                ]);
                
                return ($todayDay == $springStartDay && $todayMonth == $springStartMonth);
            }
        } else {
            // Autumn term check
            if ($categorySettings->getExecOnlyOnStartTimeAutumnTerm()) {
                // Only allow on the exact autumn start day
                $autumnStartDay = $categorySettings->getStartTimeAutumnTermDay();
                $autumnStartMonth = $categorySettings->getStartTimeAutumnTermMonth();
                
                $this->logger->debug("Autumn term restricted to start day check", [
                    'today' => "$todayDay/$todayMonth",
                    'autumnStart' => "$autumnStartDay/$autumnStartMonth",
                    'eventoId' => $event->idAnlass
                ]);
                
                return ($todayDay == $autumnStartDay && $todayMonth == $autumnStartMonth);
            }
        }
        
        // If we reach here, either:
        // 1. The "execute only on start day" flag is NOT set for this term, or
        // 2. We're checking a different term than the one with restrictions
        // In both cases, we should allow creation
        return true;
    }

    /**
     * Helper to get the settings category for an event
     * 
     * @param stdClass $event
     * @return EventoCourseCategory|null
     */
    private function getSettingsCategory(stdClass $event): ?EventoCourseCategory
    {
        // Try to find by Veranstalter ID
        if (!empty($event->anlassVeranstalter)) {
            $categories = \core_course_category::get_all(['idnumber' => $event->anlassVeranstalter]);
            
            if (!empty($categories)) {
                foreach ($categories as $cat) {
                    try {
                        $eventoCat = EventoCourseCategory::get($cat->id);
                        if ($eventoCat->getEventoSettings() !== null) {
                            return $eventoCat;
                        }
                    } catch (Exception $e) {
                        $this->logger->debug("Error getting category", [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Gets the timestamp for midnight today
     *
     * @return int
     */
    private function getMidnightTimestamp(): int
    {
        $now = time();
        return mktime(0, 0, 0, date("m", $now), date("d", $now), date("Y", $now));
    }

    /**
     * Calculates the term period based on settings
     *
     * @param int $startDay
     * @param int $startMonth
     * @param int $endDay
     * @param int $endMonth
     * @param int $now
     * @param int $eventStart
     * @return array
     */
    private function getTermPeriod(
        int $startDay,
        int $startMonth,
        int $endDay,
        int $endMonth,
        int $now,
        int $eventStart
    ): array {
        $eventYear = (int)date('Y', $eventStart);
        $currentYear = (int)date('Y', $now);

        $year = $eventYear >= $currentYear ? $eventYear : $currentYear;

        $start = mktime(0, 0, 0, $startMonth, $startDay, $year);
        $end = mktime(0, 0, 0, $endMonth, $endDay, $year);

        if ($start > $end) {
            if ($now < $start) {
                $start = mktime(0, 0, 0, $startMonth, $startDay, $year - 1);
            } else {
                $end = mktime(0, 0, 0, $endMonth, $endDay, $year + 1);
            }
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Checks if a given time is within a period
     *
     * @param int $time
     * @param array $period
     * @return bool
     */
    private function isTimeInPeriod(int $time, array $period): bool
    {
        return ($time >= $period['start'] && $time <= $period['end']);
    }
}
