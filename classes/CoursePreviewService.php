<?php

namespace local_eventocoursecreation;

use DateTime;
use Exception;
use local_eventocoursecreation\api\EventoApiCache;
use local_eventocoursecreation\exceptions\ValidationException;
use progress_trace;
use stdClass;

/**
 * Service for course preview functionality
 */
class CoursePreviewService
{
    /** @var EventoLogger */
    private EventoLogger $logger;
    
    /** @var \local_evento_evento_service */
    private \local_evento_evento_service $eventoService;
    
    /** @var CategoryManager */
    private CategoryManager $categoryManager;
    
    /** @var CourseRepository */
    private CourseRepository $courseRepository;
    
    /** @var EventoConfiguration */
    private EventoConfiguration $config;
    
    /** @var CourseCreationService */
    private CourseCreationService $courseCreationService;
    
    /** @var progress_trace */
    private progress_trace $trace;

    /**
     * Constructor
     *
     * @param EventoLogger $logger
     * @param \local_evento_evento_service $eventoService
     * @param CategoryManager $categoryManager
     * @param CourseRepository $courseRepository
     * @param EventoConfiguration $config
     * @param CourseCreationService $courseCreationService
     * @param progress_trace $trace
     */
    public function __construct(
        EventoLogger $logger,
        \local_evento_evento_service $eventoService,
        CategoryManager $categoryManager,
        CourseRepository $courseRepository,
        EventoConfiguration $config,
        CourseCreationService $courseCreationService,
        progress_trace $trace
    ) {
        $this->logger = $logger;
        $this->eventoService = $eventoService;
        $this->categoryManager = $categoryManager;
        $this->courseRepository = $courseRepository;
        $this->config = $config;
        $this->courseCreationService = $courseCreationService;
        $this->trace = $trace;
    }

    /**
     * Get preview data for a category
     *
     * @param int $categoryId The category ID
     * @param bool $refresh Whether to force refresh the data
     * @return array The preview data
     */
    public function getPreviewData(int $categoryId, bool $refresh = false): array
    {
        global $DB;
        
        $result = [
            'status' => false,
            'message' => '',
            'courses' => []
        ];

        try {
            // Get the category
            $category = EventoCourseCategory::get($categoryId);
            if (!$category->allowsEventoCourseCreation()) {
                $result['message'] = get_string('categorynotenabledforevento', 'local_eventocoursecreation');
                return $result;
            }

            // Initialize connection to Evento
            if (!$this->eventoService->init_call()) {
                $result['message'] = get_string('connectionfailed', 'local_eventocoursecreation');
                return $result;
            }

            // Get veranstalter ID from category
            $veranstalterId = $category->getIdNumber();
            if (empty($veranstalterId)) {
                $result['message'] = get_string('categoryhasnoidentifier', 'local_eventocoursecreation');
                return $result;
            }
            
            // Get events from Evento
            $this->trace->output("Fetching events for Veranstalter: {$veranstalterId}");
            $events = $this->eventoService->get_events_by_veranstalter_years($veranstalterId);
            
            if (empty($events)) {
                $this->trace->output("No events found for Veranstalter: {$veranstalterId}");
                $result['status'] = true;
                $result['message'] = get_string('noeventsfound', 'local_eventocoursecreation');
                return $result;
            }

            // Process events for preview
            $courses = [];
            
            foreach ($events as $event) {
                // Direct database check to avoid stale cache issues
                $exists = $DB->record_exists('course', ['idnumber' => $event->idAnlass]);
                
                if (!$exists) {
                    $courseData = $this->processEventForPreview($event, $category);
                    if ($courseData) {
                        $courses[] = $courseData;
                    }
                }
            }

            // Sort courses - ready first, then by date
            usort($courses, function($a, $b) {
                if ($a['canCreate'] !== $b['canCreate']) {
                    return $b['canCreate'] <=> $a['canCreate']; // Creatable first
                }
                return $a['startdate'] <=> $b['startdate']; // Then by date
            });

            $result['status'] = true;
            $result['courses'] = $courses;
            $result['message'] = count($courses) > 0 
                ? get_string('foundcoursestocreate', 'local_eventocoursecreation', count($courses))
                : get_string('nocoursestocreate', 'local_eventocoursecreation');
        } catch (Exception $e) {
            $this->logger->error("Preview generation failed", [
                'error' => $e->getMessage(),
                'categoryId' => $categoryId
            ]);
            $result['message'] = get_string('previewfailed', 'local_eventocoursecreation');
        }

        return $result;
    }

    /**
     * Process an event for preview display
     *
     * @param stdClass $event The Evento event
     * @param EventoCourseCategory $category The category
     * @return array|null The processed course data or null if invalid
     */
    private function processEventForPreview(stdClass $event, EventoCourseCategory $category): ?array
    {
        $previewMode = true;
        try {
            $startDate = strtotime($event->anlassDatumVon ?? 0);
            $endDate = strtotime($event->anlassDatumBis ?? 0);

            // Skip invalid events
            if (empty($startDate) || empty($endDate)) {
                return null;
            }

            // Find subcourses
            $subcourses = $this->findSubcourses($event);

            // Check if this is a sub-event
            $isSubEvent = !empty($event->anlass_Zusatz15) && $event->anlass_Zusatz15 != $event->idAnlass;
            $parentEventId = $isSubEvent ? (int)$event->anlass_Zusatz15 : null;
            $parentCourseExists = false;
            $parentCourseMissing = false;
            
            // If this is a sub-event, check if parent course exists
            if ($isSubEvent) {
                $parentCourse = $this->courseRepository->findByEventoId($parentEventId);
                $parentCourseExists = ($parentCourse !== null);
                $parentCourseMissing = !$parentCourseExists;
            }

            // Get target category
            $targetCategory = $this->categoryManager->getPeriodSubcategory(
                $event, 
                $category,
                $this->config,
                $previewMode  // Set preview mode to true
            );

            // Create course naming
            $categorySettings = EventoCategorySettings::getForCategory($category->getId());
            $naming = new CourseNaming(
                $event,
                $categorySettings,
                $this->courseRepository
            );

            // Default status
            $canCreate = true;
            $createBlockReason = '';
            $statusClass = 'status-ready';
            $statusText = get_string('statusready', 'local_eventocoursecreation');

            // Validate event
            try {
                $this->validateEvent($event);
            } catch (ValidationException $e) {
                $canCreate = false;
                $createBlockReason = $e->getMessage();
                $statusClass = 'status-warning';
                $statusText = get_string('statusblocked', 'local_eventocoursecreation');
            }

            // Check creation period
            $isInCreationPeriod = $this->isInCreationPeriod($event);
            if ($canCreate && !$isInCreationPeriod) {
                $canCreate = false;
                $createBlockReason = get_string('notincreationperiod', 'local_eventocoursecreation');
                $statusClass = 'status-warning';
                $statusText = get_string('statuswaiting', 'local_eventocoursecreation');
            }
            
            // Check if parent course exists for sub-events
            if ($canCreate && $isSubEvent && !$parentCourseExists) {
                $canCreate = false;
                $createBlockReason = get_string('parentcoursenotexists', 'local_eventocoursecreation');
                $statusClass = 'status-warning';
                $statusText = get_string('statuswaiting', 'local_eventocoursecreation');
            }

            return [
                'eventId' => (int)$event->idAnlass,
                'name' => $naming->getLongName(),
                'shortname' => $naming->getUniqueShortName(),
                'startdate' => $startDate,
                'enddate' => $endDate,
                'formattedStartDate' => userdate($startDate),
                'formattedEndDate' => userdate($endDate),
                'categoryId' => $targetCategory->getId(),
                'categoryname' => $targetCategory->getName(),
                'canCreate' => $canCreate,
                'createBlockReason' => $createBlockReason,
                'statusClass' => $statusClass,
                'statusText' => $statusText,
                'subcourses' => $subcourses,
                'isSubEvent' => $isSubEvent,
                'parentEventId' => $parentEventId,
                'parentCourseExists' => $parentCourseExists,
                'parentCourseMissing' => $parentCourseMissing,
                'isInCreationPeriod' => $isInCreationPeriod,
                'raw' => $event
            ];
        } catch (Exception $e) {
            $this->logger->error("Failed to process event for preview", [
                'eventId' => $event->idAnlass ?? 0,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Find subcourses for an event
     *
     * @param stdClass $event The main event
     * @return array List of subcourse data
     */
    private function findSubcourses(stdClass $event): array
    {
        $eventId = $event->idAnlass ?? 0;
        $veranstalterId = $event->anlassVeranstalter ?? '';
        
        if (empty($eventId) || empty($veranstalterId)) {
            return [];
        }
        
        try {
            $allEvents = $this->eventoService->get_events_by_veranstalter($veranstalterId);
            
            $subcourses = [];
            foreach ($allEvents as $subEvent) {
                if (isset($subEvent->anlass_Zusatz15) 
                    && $subEvent->anlass_Zusatz15 == $eventId
                    && $subEvent->idAnlass != $eventId) {
                    
                    $subcourses[] = [
                        'eventId' => (int)$subEvent->idAnlass,
                        'name' => $subEvent->anlassBezeichnung ?? ''
                    ];
                }
            }
            
            return $subcourses;
        } catch (Exception $e) {
            $this->logger->error("Failed to find subcourses", [
                'eventId' => $eventId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Validate an event for course creation
     *
     * @param stdClass $event The event to validate
     * @throws ValidationException If validation fails
     */
    private function validateEvent(stdClass $event): void
    {
        if (empty($event->anlassDatumVon) || empty($event->anlassDatumBis)) {
            throw new ValidationException(get_string('eventdatesmissing', 'local_eventocoursecreation'));
        }

        if (strtotime($event->anlassDatumBis) < time()) {
            throw new ValidationException(get_string('eventendpast', 'local_eventocoursecreation'));
        }

        // if (isset($event->anlass_Zusatz15) && $event->anlass_Zusatz15 !== $event->idAnlass) {
        //     throw new ValidationException(get_string('eventissubevent', 'local_eventocoursecreation'));
        // }
        
        // Skip the course existence check here because we've already done it in getPreviewData
    }

    /**
     * Check if the event is in the creation period
     *
     * @param stdClass $event The event to check
     * @return bool Whether the event is in creation period
     */
    private function isInCreationPeriod(stdClass $event): bool
    {
        $termSettings = $this->config->getTermSettings();
        $eventStart = strtotime($event->anlassDatumVon);
        $now = $this->getMidnightTimestamp();
        $year = (int)date('Y', $eventStart);

        // Check spring term period
        $springStart = $termSettings->getSpringTermStartTimestamp($year);
        $springEnd = $termSettings->getSpringTermEndTimestamp($year);
        if ($this->isTimeInPeriod($now, ['start' => $springStart, 'end' => $springEnd])) {
            return true;
        }

        // Check autumn term period
        $autumnStart = $termSettings->getAutumnTermStartTimestamp($year);
        $autumnEnd = $termSettings->getAutumnTermEndTimestamp($year);
        return $this->isTimeInPeriod($now, ['start' => $autumnStart, 'end' => $autumnEnd]);
    }

    /**
     * Get midnight timestamp
     *
     * @return int The midnight timestamp
     */
    private function getMidnightTimestamp(): int
    {
        $now = time();
        return mktime(0, 0, 0, date("m", $now), date("d", $now), date("Y", $now));
    }

    /**
     * Check if a time is within a period
     *
     * @param int $time The time to check
     * @param array $period The period with 'start' and 'end' keys
     * @return bool Whether the time is in the period
     */
    private function isTimeInPeriod(int $time, array $period): bool
    {
        return ($time >= $period['start'] && $time <= $period['end']);
    }

    /**
     * Create a single course
     *
     * @param int $eventId The event ID
     * @param int $categoryId The category ID
     * @param bool $force Whether to force creation
     * @return bool Whether creation was successful
     */
    public function createSingleCourse(int $eventId, int $categoryId, bool $force = false): bool
    {
        $previewMode = false; // Not in preview mode for actual creation
        try {
            $this->trace->output("Creating course for event {$eventId} in category {$categoryId}");
            
            // Get category
            $category = EventoCourseCategory::get($categoryId);
            
            // Initialize connection
            if (!$this->eventoService->init_call()) {
                $this->trace->output("Failed to connect to Evento service");
                return false;
            }
            
            // Find the event
            $events = $this->eventoService->get_events_by_veranstalter_years($category->getIdNumber());
            $event = null;
            
            foreach ($events as $evt) {
                if ((int)$evt->idAnlass === $eventId) {
                    $event = $evt;
                    break;
                }
            }
            
            if (!$event) {
                $this->trace->output("Event {$eventId} not found");
                return false;
            }
            
            // If not forcing creation, validate
            if (!$force) {
                // Check if this is a sub-event
                $isSubEvent = !empty($event->anlass_Zusatz15) && $event->anlass_Zusatz15 != $event->idAnlass;
                
                // If sub-event, verify parent course exists
                if ($isSubEvent) {
                    $parentEventId = (int)$event->anlass_Zusatz15;
                    $parentCourse = $this->courseRepository->findByEventoId($parentEventId);
                    
                    if (!$parentCourse) {
                        $this->trace->output("Parent course for event {$parentEventId} does not exist");
                        return false;
                    }
                }
                
                // Check if in creation period
                if (!$this->isInCreationPeriod($event)) {
                    $this->trace->output("Event is not in creation period");
                    return false;
                }
                
                // Validate the event
                try {
                    $this->validateEvent($event);
                } catch (ValidationException $e) {
                    $this->trace->output("Event validation failed: " . $e->getMessage());
                    return false;
                }
            }
            
            // Get target category (using previewMode = false for actual creation)
            $targetCategory = $this->categoryManager->getPeriodSubcategory(
                $event,
                $category,
                $this->config,
                $previewMode
            );
            
            // Create the course
            $course = $this->courseCreationService->createCourse($event, $targetCategory, $force);
            
            return $course !== null;
        } catch (Exception $e) {
            $this->logger->error("Course creation failed", [
                'eventId' => $eventId,
                'categoryId' => $categoryId,
                'error' => $e->getMessage()
            ]);
            $this->trace->output("Error: " . $e->getMessage());
            return false;
        }
    }
}