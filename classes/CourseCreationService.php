<?php

namespace local_eventocoursecreation;

use DateTime;
use Exception;
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
     * Constructor
     */
    public function __construct(
        EventoConfiguration $config,
        CourseRepository $courseRepository,
        EnrollmentManager $enrollmentManager,
        EventoLogger $logger,
        local_evento_evento_service $eventoService,
        CategoryManager $categoryManager,
        TemplateManager $templateManager
    ) {
        $this->config = $config;
        $this->courseRepository = $courseRepository;
        $this->enrollmentManager = $enrollmentManager;
        $this->logger = $logger;
        $this->eventoService = $eventoService;
        $this->categoryManager = $categoryManager;
        $this->templateManager = $templateManager;
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

            // Create category hierarchy and get category mapping
            $categoryMap = $this->categoryManager->createMinimalHierarchy(
                $veranstalterList,
                function($ver) {
                    return !empty($this->eventoService->get_events_by_veranstalter_years($ver->IDBenutzer));
                }
            );

            // Process events for each category
            foreach ($categoryMap as $veranstalterId => $categoryId) {
                try {
                    $category = EventoCourseCategory::get($categoryId);
                    $this->processCategoryEvents($category, $veranstalterId);
                } catch (Exception $e) {
                    $this->logger->error("Failed to process category", [
                        'categoryId' => $categoryId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->logger->info("Synchronization completed successfully");
            return 0;
        } catch (Exception $e) {
            $this->logger->error("Synchronization failed", [
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

    /**
     * Processes events within a category
     *
     * @param EventoCourseCategory $category The category to process
     * @param string $veranstalterId The Veranstalter ID associated with the category
     */
    private function processCategoryEvents(EventoCourseCategory $category, string $veranstalterId): void
    {
        $events = $this->eventoService->get_events_by_veranstalter_years($veranstalterId);
        if (empty($events)) {
            return;
        }

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
            $this->validateEvent($event);

            if (!$force && !$this->canCreateCourse($event)) {
                return null;
            }

            $course = $this->buildCourseObject($event, $category);
            $savedCourse = $this->courseRepository->save($course);

            $this->postCreationSetup($savedCourse, $event);

            $this->logger->info("Course created successfully", [
                'courseId' => $savedCourse->id,
                'eventoId' => $event->idAnlass,
                'category' => $category->getId()
            ]);

            return $savedCourse;
        } catch (ValidationException $e) {
            $this->logger->error("Course creation validation failed", [
                'error' => $e->getMessage(),
                'eventoId' => $event->idAnlass
            ]);
            return null;
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
     * @return stdClass
     */
    private function buildCourseObject(stdClass $event, EventoCourseCategory $category): stdClass
    {
        // Get category-specific settings
        $categorySettings = EventoCategorySettings::getForCategory($category->getId());

        
        
        // Get global settings for naming templates
        $courseSettings = $this->config->getCourseSettings();

        // Create naming using category settings but global templates
        $naming = new CourseNaming(
            $event,
            $categorySettings,
            $this->courseRepository
        );

        $course = new stdClass();
        $course->category = $category->getId();
        $course->idnumber = (string)$event->idAnlass;
        $course->fullname = $naming->getLongName();
        $course->shortname = $naming->getUniqueShortName();
        
        // Use category-specific settings where available
        $course->visible = $categorySettings->getCourseVisibility();
        $course->newsitems = $categorySettings->getNewsItemsNumber();
        $course->numsections = $categorySettings->getNumberOfSections();

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
     * Performs post-creation setup tasks
     *
     * @param stdClass $course
     * @param stdClass $event
     */
    private function postCreationSetup(stdClass $course, stdClass $event): void
    {
        $courseSettings = $this->config->getCourseSettings();

        if ($courseSettings->isTemplateCourseEnabled() && $courseSettings->getTemplateCourse()) {
            $this->templateManager->restoreTemplate($course->id, $courseSettings->getTemplateCourse());
        }

        $this->enrollmentManager->createEnrollmentInstance($course, $event);
        $this->handleSubEvents($course, $event);
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
