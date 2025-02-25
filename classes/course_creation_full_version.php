<?php
// /**
//  * Evento course creation plugin - Refactored Version
//  * 
//  * Handles automatic course creation and management based on Evento system data.
//  * Implements improved architecture with better separation of concerns,
//  * dependency injection, and error handling.
//  *
//  * @package    local_eventocoursecreation
//  * @copyright  2024 FHGR Julien Rädler
//  * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
//  */

// namespace local_eventocoursecreation;

// defined('MOODLE_INTERNAL') || die();

// require_once($CFG->dirroot . '/local/evento/classes/evento_service.php');
// require_once($CFG->libdir . '/weblib.php');
// require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
// require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

// // Constants for naming placeholders
// const NAME_PH_EVENTO_NAME = '{EVENTO_NAME}';
// const NAME_PH_EVENTO_ABR = '{EVENTO_ABR}';
// const NAME_PH_PERIOD = '{PERIOD}';
// const NAME_PH_COS = '{COS}';
// const NAME_PH_NUM = '{NUM}';

// /**
//  * Custom exceptions for better error handling
//  */
// class EventoException extends \Exception {}
// class CourseCreationException extends EventoException {}
// class CategoryCreationException extends EventoException {}
// class ValidationException extends EventoException {}

// // /**
// //  * Configuration handler for Evento course creation
// //  */
// // class EventoConfiguration {
// //     private array $config;
    
// //     public function __construct() {
// //         $this->config = get_config('local_eventocoursecreation');
// //     }
    
// //     public function isPluginEnabled(): bool {
// //         return !empty($this->config->enableplugin);
// //     }
    
// //     public function getCourseDefaultSettings(): CourseSettings {
// //         return new CourseSettings(
// //             visibility: $this->config->coursevisibility ?? 1,
// //             newsItems: $this->config->newsitemsnumber ?? 5,
// //             sections: $this->config->numberofsections ?? 10,
// //             useTemplate: $this->config->enablecoursetemplate ?? 0,
// //             templateId: $this->config->templatecourse ?? null,
// //             useCustomStartTime: $this->config->setcustomcoursestarttime ?? 0,
// //             customStartTime: $this->config->starttimecourse ?? 0,
// //             subcategorization: $this->config->subcategorization ?? 1,
// //             longNameTemplate: $this->config->longcoursenaming ?? '{EVENTO_NAME}',
// //             shortNameTemplate: $this->config->shortcoursenaming ?? '{EVENTO_ABR}-{PERIOD}'
// //         );
// //     }
    
// //     public function getTermSettings(): TermSettings {
// //         return new TermSettings(
// //             springStartDay: $this->config->starttimespringtermday ?? 1,
// //             springStartMonth: $this->config->starttimespringtermmonth ?? 2,
// //             springEndDay: $this->config->endtimespringtermday ?? 31,
// //             springEndMonth: $this->config->endtimespringtermmonth ?? 7,
// //             autumnStartDay: $this->config->starttimeautumntermday ?? 1,
// //             autumnStartMonth: $this->config->starttimeautumntermmonth ?? 8,
// //             autumnEndDay: $this->config->endtimeautumntermday ?? 31,
// //             autumnEndMonth: $this->config->endtimeautumntermmonth ?? 1
// //         );
// //     }
// // }

// // /**
// //  * Value object for course settings
// //  */
// // class CourseSettings {
// //     public function __construct(
// //         public readonly bool $visibility,
// //         public readonly int $newsItems,
// //         public readonly int $sections,
// //         public readonly bool $useTemplate,
// //         public readonly ?int $templateId,
// //         public readonly bool $useCustomStartTime,
// //         public readonly int $customStartTime,
// //         public readonly int $subcategorization,
// //         public readonly string $longNameTemplate,
// //         public readonly string $shortNameTemplate
// //     ) {}
// // }

// // /**
// //  * Value object for term timing settings
// //  */
// // class TermSettings {
// //     public function __construct(
// //         public readonly int $springStartDay,
// //         public readonly int $springStartMonth,
// //         public readonly int $springEndDay,
// //         public readonly int $springEndMonth,
// //         public readonly int $autumnStartDay,
// //         public readonly int $autumnStartMonth,
// //         public readonly int $autumnEndDay,
// //         public readonly int $autumnEndMonth
// //     ) {}
// // }

// /**
//  * Enhanced logging capabilities
//  */
// class EventoLogger {
//     private \progress_trace $trace;
    
//     public function __construct(\progress_trace $trace) {
//         $this->trace = $trace;
//     }
    
//     public function info(string $message, array $context = []): void {
//         $this->log('INFO', $message, $context);
//     }
    
//     public function error(string $message, array $context = []): void {
//         $this->log('ERROR', $message, $context);
//     }
    
//     public function debug(string $message, array $context = []): void {
//         if (debugging('', DEBUG_DEVELOPER)) {
//             $this->log('DEBUG', $message, $context);
//         }
//     }
    
//     private function log(string $level, string $message, array $context): void {
//         $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
//         $this->trace->output(sprintf(
//             '[%s] [%s] %s%s',
//             date('Y-m-d H:i:s'),
//             $level,
//             $message,
//             $contextStr
//         ));
//     }
// }

// /**
//  * Caching implementation
//  */
// class EventoCache {
//     private \cache $cache;
    
//     public function __construct() {
//         $this->cache = \cache::make('local_eventocoursecreation', 'coursecreation');
//     }
    
//     public function get(string $key) {
//         return $this->cache->get($key);
//     }
    
//     public function set(string $key, $value): void {
//         $this->cache->set($key, $value);
//     }
    
//     public function delete(string $key): void {
//         $this->cache->delete($key);
//     }
// }

// /**
//  * Repository interface for course data access
//  */
// interface CourseRepositoryInterface {
//     public function findByEventoId(int $eventoId): ?\stdClass;
//     public function findByShortName(string $shortName): ?\stdClass;
//     public function save(\stdClass $course): \stdClass;
// }

// /**
//  * Database implementation of course repository
//  */
// class CourseRepository implements CourseRepositoryInterface {
//     private \moodle_database $db;
//     private EventoCache $cache;
//     private EventoLogger $logger;
    
//     public function __construct(\moodle_database $db, EventoCache $cache, EventoLogger $logger) {
//         $this->db = $db;
//         $this->cache = $cache;
//         $this->logger = $logger;
//     }
    
//     public function findByEventoId(int $eventoId): ?\stdClass {
//         $cacheKey = "course_evento_{$eventoId}";
//         $cached = $this->cache->get($cacheKey);
        
//         if ($cached !== false) {
//             return $cached;
//         }
        
//         $course = $this->db->get_record('course', ['idnumber' => $eventoId]);
        
//         if ($course) {
//             $this->cache->set($cacheKey, $course);
//         }
        
//         return $course ?: null;
//     }
    
//     public function findByShortName(string $shortName): ?\stdClass {
//         return $this->db->get_record('course', ['shortname' => $shortName]) ?: null;
//     }
    
//     public function save(\stdClass $course): \stdClass {
//         try {
//             if (empty($course->id)) {
//                 $course->id = $this->db->insert_record('course', $course);
//             } else {
//                 $this->db->update_record('course', $course);
//             }
            
//             if (!empty($course->idnumber)) {
//                 $this->cache->delete("course_evento_{$course->idnumber}");
//             }
            
//             return $course;
//         } catch (\dml_exception $e) {
//             $this->logger->error('Failed to save course', [
//                 'error' => $e->getMessage(),
//                 'course' => $course
//             ]);
//             throw new CourseCreationException('Failed to save course: ' . $e->getMessage(), 0, $e);
//         }
//     }
// }

// /**
//  * Handles course template operations
//  */
// class TemplateManager {
//     private EventoLogger $logger;
//     private EventoCache $cache;
    
//     public function __construct(EventoLogger $logger, EventoCache $cache) {
//         $this->logger = $logger;
//         $this->cache = $cache;
//     }
    
//     public function restoreTemplate(int $courseId, int $templateId): bool {
//         global $USER;
        
//         try {
//             $backupdir = $this->getTemplateBackupDir($templateId);
//             if (!$backupdir) {
//                 throw new \Exception("Could not get template backup directory");
//             }
            
//             $tempdir = make_backup_temp_directory($backupdir);
//             $rc = new \restore_controller(
//                 $backupdir,
//                 $courseId,
//                 \backup::INTERACTIVE_NO,
//                 \backup::MODE_GENERAL,
//                 $USER->id,
//                 \backup::TARGET_EXISTING_ADDING
//             );
            
//             $this->configureRestore($rc);
            
//             if ($rc->execute_precheck()) {
//                 $rc->execute_plan();
//                 $this->logger->info("Template restored successfully", [
//                     'courseId' => $courseId,
//                     'templateId' => $templateId
//                 ]);
//                 return true;
//             }
            
//             throw new \Exception("Restore precheck failed");
            
//         } catch (\Exception $e) {
//             $this->logger->error("Template restore failed", [
//                 'error' => $e->getMessage(),
//                 'courseId' => $courseId,
//                 'templateId' => $templateId
//             ]);
//             return false;
//         }
//     }
    
//     private function configureRestore(\restore_controller $rc): void {
//         $rc->get_plan()->get_setting('users')->set_value(false);
//         $rc->get_plan()->get_setting('user_files')->set_value(false);
//         if ($rc->get_plan()->setting_exists('role_assignments')) {
//             $rc->get_plan()->get_setting('role_assignments')->set_value(false);
//         }
//     }
    
//     private function getTemplateBackupDir(int $templateId): ?string {
//         $cacheKey = "template_backup_{$templateId}";
//         return $this->cache->get($cacheKey) ?? $this->createTemplateBackup($templateId);
//     }
    
//     private function createTemplateBackup(int $templateId): ?string {
//         // Template backup creation logic here
//         // Returns backup directory path or null on failure
//         return null;
//     }
// }

// /**
//  * Handles enrollment operations
//  */
// class EnrollmentManager {
//     private \moodle_database $db;
//     private EventoLogger $logger;
    
//     public function __construct(\moodle_database $db, EventoLogger $logger) {
//         $this->db = $db;
//         $this->logger = $logger;
//     }
    
//     public function createEnrollmentInstance(\stdClass $course, \stdClass $event): bool {
//         global $CFG;
//         require_once($CFG->dirroot . '/enrol/evento/lib.php');
        
//         try {
//             $enrol = enrol_get_plugin('evento');
//             $fields = $enrol->get_instance_defaults();
//             $fields['customint4'] = $event->idAnlass;
//             $fields['customtext1'] = $event->anlassNummer;
            
//             if (isset($event->anlass_Zusatz15) && $event->anlass_Zusatz15 !== $event->idAnlass) {
//                 $fields['name'] = 'Evento Parallelanlass';
//             }
            
//             if ($enrol->add_instance($course, $fields)) {
//                 $this->logger->info("Enrollment instance created", [
//                     'courseId' => $course->id,
//                     'eventoId' => $event->idAnlass
//                 ]);
//                 return true;
//             }
            
//             throw new \Exception("Failed to add enrollment instance");
            
//         } catch (\Exception $e) {
//             $this->logger->error("Failed to create enrollment instance", [
//                 'error' => $e->getMessage(),
//                 'courseId' => $course->id,
//                 'eventoId' => $event->idAnlass
//             ]);
//             return false;
//         }
//     }
    
//     public function hasEventoEnrollment(int $courseId, int $eventoId): bool {
//         return $this->db->record_exists('enrol', [
//             'courseid' => $courseId,
//             'customint4' => $eventoId,
//             'enrol' => 'evento'
//         ]);
//     }
// }

// /**
//  * Main course creation service
//  */
// class CourseCreationService {
//     private EventoConfiguration $config;
//     private CourseRepository $courseRepository;
//     private TemplateManager $templateManager;
//     private EnrollmentManager $enrollmentManager;
//     private EventoLogger $logger;
//     private \local_evento_evento_service $eventoService;
//     private CategoryManager $categoryManager;
    
//     public function __construct(
//         EventoConfiguration $config,
//         CourseRepository $courseRepository,
//         TemplateManager $templateManager,
//         EnrollmentManager $enrollmentManager,
//         EventoLogger $logger,
//         \local_evento_evento_service $eventoService,
//         CategoryManager $categoryManager
//     ) {
//         $this->config = $config;
//         $this->courseRepository = $courseRepository;
//         $this->templateManager = $templateManager;
//         $this->enrollmentManager = $enrollmentManager;
//         $this->logger = $logger;
//         $this->eventoService = $eventoService;
//         $this->categoryManager = $categoryManager;
//     }

//     public function synchronizeAll(): int {
//         try {
//             if (!$this->config->isPluginEnabled()) {
//                 $this->logger->info("Plugin is disabled");
//                 return 2;
//             }
    
//             if (!$this->eventoService->init_call()) {
//                 $this->logger->error("Failed to initialize Evento connection");
//                 return 2;
//             }
    
//             $veranstalterList = $this->eventoService->get_active_veranstalter();
//             if (empty($veranstalterList)) {
//                 $this->logger->info("No active Veranstalter found");
//                 return 2;
//             }
    
//             // Process categories
//             $categories = $this->categoryManager->createMinimalHierarchy(
//                 $veranstalterList, 
//                 fn($ver) => !empty($this->eventoService->get_events_by_veranstalter_years($ver->IDBenutzer))
//             );
    
//             foreach ($categories as $categoryId) {
//                 $category = \core_course_category::get($categoryId);
//                 $this->processCategoryEvents($category);
//             }
    
//             $this->logger->info("Synchronization completed successfully");
//             return 0;
    
//         } catch (\Exception $e) {
//             $this->logger->error("Synchronization failed", [
//                 'error' => $e->getMessage()
//             ]);
//             return 1;
//         }
//     }
    
//     private function processCategoryEvents(\core_course_category $category): void {
//         // Simple check that category exists and has idnumber
//         if (empty($category->idnumber)) {
//             $this->logger->error("Category missing idnumber", ['categoryId' => $category->id]);
//             return;
//         }
    
//         $events = $this->eventoService->get_events_by_veranstalter_years($category->idnumber);
//         if (empty($events)) {
//             return;
//         }
    
//         foreach ($events as $event) {
//             try {
//                 $this->createCourse($event, $category);
//             } catch (\Exception $e) {
//                 $this->logger->error("Failed to process event", [
//                     'eventId' => $event->idAnlass,
//                     'error' => $e->getMessage()
//                 ]);
//             }
//         }
//     }
    
//     public function createCourse(\stdClass $event, \core_course_category $category, bool $force = false): ?\stdClass {
//         try {
//             $this->validateEvent($event);
            
//             if (!$force && !$this->canCreateCourse($event)) {
//                 return null;
//             }
            
//             $course = $this->prepareCourse($event, $category);
//             $course = $this->courseRepository->save($course);
            
//             $this->setupCourse($course, $event);
            
//             $this->logger->info("Course created successfully", [
//                 'courseId' => $course->id,
//                 'eventoId' => $event->idAnlass
//             ]);
            
//             return $course;
            
//         } catch (ValidationException $e) {
//             $this->logger->error("Course creation validation failed", [
//                 'error' => $e->getMessage(),
//                 'eventoId' => $event->idAnlass
//             ]);
//             return null;
//         } catch (\Exception $e) {
//             $this->logger->error("Course creation failed", [
//                 'error' => $e->getMessage(),
//                 'eventoId' => $event->idAnlass
//             ]);
//             throw new CourseCreationException($e->getMessage(), 0, $e);
//         }
//     }
    
//     private function validateEvent(\stdClass $event): void {
//         if (empty($event->anlassDatumVon) || empty($event->anlassDatumBis)) {
//             throw new ValidationException("Event dates are missing");
//         }
        
//         if (strtotime($event->anlassDatumBis) < time()) {
//             throw new ValidationException("Event end date is in the past");
//         }
        
//         if (isset($event->anlass_Zusatz15) && $event->anlass_Zusatz15 !== $event->idAnlass) {
//             throw new ValidationException("Event is a sub-event");
//         }
        
//         if ($this->courseRepository->findByEventoId($event->idAnlass)) {
//             throw new ValidationException("Course already exists for this event");
//         }
//     }
    
//     private function canCreateCourse(\stdClass $event): bool {
//         if (!$this->config->isPluginEnabled()) {
//             return false;
//         }
        
//         $settings = $this->config->getCourseDefaultSettings();
//         if (!$this->isInCreationPeriod($event)) {
//             $this->logger->info("Not in creation period", ['eventoId' => $event->idAnlass]);
//             return false;
//         }
        
//         return true;
//     }
    
//     private function prepareCourse(\stdClass $event, \core_course_category $category): \stdClass {
//         $settings = $this->config->getCourseDefaultSettings();
//         $naming = new CourseNaming($event, $settings, $this->courseRepository);
        
//         $course = new \stdClass();
//         $course->category = $category->id;
//         $course->idnumber = (string)$event->idAnlass;
//         $course->fullname = $naming->getLongName();
//         $course->shortname = $naming->getUniqueShortName();
//         $course->visible = $settings->visibility;
//         $course->newsitems = $settings->newsItems;
//         $course->numsections = $settings->sections;
        
//         $this->setCourseDates($course, $event);
        
//         return $course;
//     }
    
//     private function setCourseDates(\stdClass $course, \stdClass $event): void {
//         $course->startdate = strtotime($event->anlassDatumVon);
//         $course->enddate = strtotime($event->anlassDatumBis);
        
//         $settings = $this->config->getCourseDefaultSettings();
//         if ($settings->useCustomStartTime) {
//             $course->startdate = $settings->customStartTime;
//         }
//     }
    
//     private function setupCourse(\stdClass $course, \stdClass $event): void {
//         $settings = $this->config->getCourseDefaultSettings();
        
//         if ($settings->useTemplate && $settings->templateId) {
//             $this->templateManager->restoreTemplate($course->id, $settings->templateId);
//         }
        
//         $this->enrollmentManager->createEnrollmentInstance($course, $event);
//         $this->handleSubEvents($course, $event);
//     }
    
//     private function handleSubEvents(\stdClass $course, \stdClass $event): void {
//         try {
//             // Find sub-events by checking anlass_Zusatz15 field
//             $allEvents = $this->eventoService->get_events_by_veranstalter($event->anlassVeranstalter);
//             $subEvents = array_filter($allEvents, function($subEvent) use ($event) {
//                 return isset($subEvent->anlass_Zusatz15) 
//                     && $subEvent->anlass_Zusatz15 === $event->idAnlass
//                     && $subEvent->idAnlass !== $event->idAnlass;
//             });
    
//             foreach ($subEvents as $subEvent) {
//                 $this->enrollmentManager->createEnrollmentInstance($course, $subEvent);
//             }
//         } catch (\Exception $e) {
//             $this->logger->error("Failed to handle sub-events", [
//                 'error' => $e->getMessage(),
//                 'courseId' => $course->id,
//                 'eventoId' => $event->idAnlass
//             ]);
//         }
//     }
    
//     private function isInCreationPeriod(\stdClass $event): bool {
//         $termSettings = $this->config->getTermSettings();
//         $eventStart = strtotime($event->anlassDatumVon);
//         $now = $this->getMidnightTimestamp();
        
//         // Check spring term
//         $spring = $this->getTermPeriod(
//             $termSettings->springStartDay,
//             $termSettings->springStartMonth,
//             $termSettings->springEndDay,
//             $termSettings->springEndMonth,
//             $now,
//             $eventStart
//         );
        
//         if ($this->isTimeInPeriod($now, $spring)) {
//             return true;
//         }
        
//         // Check autumn term
//         $autumn = $this->getTermPeriod(
//             $termSettings->autumnStartDay,
//             $termSettings->autumnStartMonth,
//             $termSettings->autumnEndDay,
//             $termSettings->autumnEndMonth,
//             $now,
//             $eventStart
//         );
        
//         return $this->isTimeInPeriod($now, $autumn);
//     }
    
//     private function getMidnightTimestamp(): int {
//         $now = time();
//         return mktime(0, 0, 0, date("m", $now), date("d", $now), date("Y", $now));
//     }
    
//     private function getTermPeriod(
//         int $startDay,
//         int $startMonth,
//         int $endDay,
//         int $endMonth,
//         int $now,
//         int $eventStart
//     ): array {
//         $eventYear = (int)date('Y', $eventStart);
//         $currentYear = (int)date('Y', $now);
        
//         $year = $eventYear >= $currentYear ? $eventYear : $currentYear;
        
//         $start = mktime(0, 0, 0, $startMonth, $startDay, $year);
//         $end = mktime(0, 0, 0, $endMonth, $endDay, $year);
        
//         if ($start > $end) {
//             if ($now < $start) {
//                 $start = mktime(0, 0, 0, $startMonth, $startDay, $year - 1);
//             } else {
//                 $end = mktime(0, 0, 0, $endMonth, $endDay, $year + 1);
//             }
//         }
        
//         return ['start' => $start, 'end' => $end];
//     }
    
//     private function isTimeInPeriod(int $time, array $period): bool {
//         return ($time >= $period['start'] && $time <= $period['end']);
//     }
// }

// /**
//  * Handles course naming logic
//  */
// class CourseNaming {
//     private \stdClass $event;
//     private CourseSettings $settings;
//     private CourseRepository $courseRepository;
//     private array $components;
    
//     public function __construct(
//         \stdClass $event,
//         CourseSettings $settings,
//         CourseRepository $courseRepository
//     ) {
//         $this->event = $event;
//         $this->settings = $settings;
//         $this->courseRepository = $courseRepository;
//         $this->components = $this->parseComponents();
//     }
    
//     public function getLongName(): string {
//         return $this->createName($this->components, true);
//     }
    
//     public function getUniqueShortName(): string {
//         $baseName = $this->createName($this->components, false);
//         return $this->makeUnique($baseName);
//     }
    
//     private function parseComponents(): array {
//         $startTime = strtotime($this->event->anlassDatumVon);
//         $moduleTokens = explode('.', $this->event->anlassNummer);
//         $moduleIdentifier = $moduleTokens[1] ?? '';
        
//         $parts = preg_split('/(?=[A-Z])/', $moduleIdentifier, -1, PREG_SPLIT_NO_EMPTY);
//         $courseOfStudies = array_shift($parts) ?? '';
//         $moduleAbr = implode('', $parts) ?: $this->event->anlassBezeichnung;
        
//         return [
//             'period' => $this->getPeriod($startTime),
//             'moduleabr' => $moduleAbr,
//             'courseofstudies' => $this->event->anlassVeranstalter ?: $courseOfStudies,
//             'num' => '',
//             'name' => $this->event->anlassBezeichnung
//         ];
//     }
    
//     private function getPeriod(int $timestamp): string {
//         if ($this->settings->useYearCategories) {
//             return date('Y', $timestamp);
//         }
        
//         $month = (int)date('n', $timestamp);
//         $year = date('y', $timestamp);
//         return ($month >= 8) ? "HS{$year}" : "FS{$year}";
//     }
    
//     private function createName(array $components, bool $isLong): string {
//         $template = $isLong ? $this->settings->longNameTemplate : $this->settings->shortNameTemplate;
        
//         $replacements = [
//             NAME_PH_EVENTO_NAME => $components['name'],
//             NAME_PH_EVENTO_ABR => $components['moduleabr'],
//             NAME_PH_PERIOD => $components['period'],
//             NAME_PH_COS => $components['courseofstudies'],
//             NAME_PH_NUM => $components['num']
//         ];
        
//         return trim(str_replace(
//             array_keys($replacements),
//             array_values($replacements),
//             $template
//         ));
//     }
    
//     private function makeUnique(string $baseName): string {
//         $currentName = $baseName;
//         $suffix = '';
//         $attempt = 1;
        
//         while ($this->courseRepository->findByShortName($currentName)) {
//             $attempt++;
//             $suffix = '_' . $attempt;
//             $currentName = $baseName . $suffix;
//         }
        
//         return $currentName;
//     }
// }

// /**
//  * Handles category management and hierarchy
//  */
// class CategoryManager {
//     private \moodle_database $db;
//     private EventoLogger $logger;
//     private EventoCache $cache;
//     private array $categoryCache = [];
    
//     public function __construct(\moodle_database $db, EventoLogger $logger, EventoCache $cache) {
//         $this->db = $db;
//         $this->logger = $logger;
//         $this->cache = $cache;
//     }
    
//     /**
//      * Creates the minimum necessary category structure based on Veranstalter data
//      *
//      * @param array $veranstalterList List of Veranstalter objects from Evento
//      * @param callable $eventChecker Callback to check if events need courses
//      * @return array Map of Veranstalter IDs to category IDs
//      */
//     public function createMinimalHierarchy(array $veranstalterList, callable $eventChecker): array {
//         $requiredCategories = $this->evaluateRequiredCategories($veranstalterList, $eventChecker);
//         return $this->createCategories($requiredCategories);
//     }
    
//     /**
//      * Determines which categories are needed based on events
//      */
//     private function evaluateRequiredCategories(array $veranstalterList, callable $eventChecker): array {
//         $required = [];
//         $veranstalterMap = [];
        
//         // Build lookup map
//         foreach ($veranstalterList as $ver) {
//             $veranstalterMap[$ver->IDBenutzer] = $ver;
//         }
        
//         // Identify directly required categories
//         foreach ($veranstalterList as $ver) {
//             if ($eventChecker($ver)) {
//                 $required[$ver->IDBenutzer] = [
//                     'veranstalter' => $ver,
//                     'hasDirectCourses' => true,
//                     'parentRequired' => false
//                 ];
                
//                 // Mark parents as required
//                 $this->markParentCategoriesRequired($ver, $veranstalterMap, $required);
//             }
//         }
        
//         return $required;
//     }
    
//     /**
//      * Recursively marks parent categories as required
//      */
//     private function markParentCategoriesRequired($veranstalter, array $veranstalterMap, array &$required): void {
//         $current = $veranstalter;
        
//         while (!empty($current->OE) && isset($veranstalterMap[$current->OE])) {
//             $parentId = $current->OE;
            
//             if (!isset($required[$parentId])) {
//                 $required[$parentId] = [
//                     'veranstalter' => $veranstalterMap[$parentId],
//                     'hasDirectCourses' => false,
//                     'parentRequired' => true
//                 ];
//             }
            
//             $current = $veranstalterMap[$parentId];
//         }
//     }
    
//     /**
//      * Creates categories based on requirements
//      */
//     private function createCategories(array $required): array {
//         $categoryMap = [];
        
//         // Process root categories first
//         foreach ($required as $id => $data) {
//             if (empty($data['veranstalter']->OE)) {
//                 $categoryMap[$id] = $this->ensureCategoryExists($data['veranstalter'], 0);
//             }
//         }
        
//         // Process remaining categories in hierarchy order
//         $remaining = array_filter($required, function($data) {
//             return !empty($data['veranstalter']->OE);
//         });
        
//         while (!empty($remaining)) {
//             foreach ($remaining as $id => $data) {
//                 $parentId = $data['veranstalter']->OE;
                
//                 if (isset($categoryMap[$parentId])) {
//                     $categoryMap[$id] = $this->ensureCategoryExists(
//                         $data['veranstalter'], 
//                         $categoryMap[$parentId]
//                     );
//                     unset($remaining[$id]);
//                 }
//             }
//         }
        
//         return $categoryMap;
//     }
    
//     /**
//      * Creates or gets existing category
//      */
//     private function ensureCategoryExists($veranstalter, int $parentId): int {
//         $cacheKey = "category_veranstalter_{$veranstalter->IDBenutzer}";
        
//         // Check memory cache
//         if (isset($this->categoryCache[$cacheKey])) {
//             return $this->categoryCache[$cacheKey];
//         }
        
//         // Check persistent cache
//         $cachedId = $this->cache->get($cacheKey);
//         if ($cachedId !== false) {
//             $this->categoryCache[$cacheKey] = $cachedId;
//             return $cachedId;
//         }
        
//         try {
//             // Check database
//             $existing = $this->db->get_record('course_categories', [
//                 'idnumber' => $veranstalter->IDBenutzer
//             ]);
            
//             if ($existing) {
//                 $categoryId = $existing->id;
//             } else {
//                 // Create new category
//                 $categorydata = new \stdClass();
//                 $categorydata->name = $veranstalter->benutzerName;
//                 $categorydata->idnumber = $veranstalter->IDBenutzer;
//                 $categorydata->parent = $parentId;
//                 $categorydata->visible = 1;
                
//                 $category = \core_course_category::create($categorydata);
//                 $categoryId = $category->id;
                
//                 $this->logger->info("Created category", [
//                     'name' => $categorydata->name,
//                     'id' => $categoryId
//                 ]);
//             }
            
//             // Cache the result
//             $this->categoryCache[$cacheKey] = $categoryId;
//             $this->cache->set($cacheKey, $categoryId);
            
//             return $categoryId;
            
//         } catch (\Exception $e) {
//             $this->logger->error("Failed to create category", [
//                 'error' => $e->getMessage(),
//                 'veranstalter' => $veranstalter->IDBenutzer
//             ]);
//             throw new CategoryCreationException(
//                 "Failed to create category: " . $e->getMessage(),
//                 0,
//                 $e
//             );
//         }
//     }
    
//     /**
//      * Gets or creates period subcategory
//      */
//     public function getPeriodSubcategory(\stdClass $event, \stdClass $parentCategory, CourseSettings $settings): \stdClass {
//         if (empty($event->anlassDatumVon)) {
//             return $parentCategory;
//         }
        
//         $periodName = $this->determinePeriodName($event, $settings);
//         $cacheKey = "subcategory_{$parentCategory->id}_{$periodName}";
        
//         // Check cache
//         $cached = $this->cache->get($cacheKey);
//         if ($cached !== false) {
//             return $cached;
//         }
        
//         try {
//             // Check if subcategory exists
//             $subcategory = $this->db->get_record('course_categories', [
//                 'parent' => $parentCategory->id,
//                 'name' => $periodName
//             ]);
            
//             if (!$subcategory) {
//                 // Create new subcategory
//                 $categorydata = new \stdClass();
//                 $categorydata->name = $periodName;
//                 $categorydata->idnumber = $parentCategory->idnumber . '_' . $periodName;
//                 $categorydata->parent = $parentCategory->id;
//                 $categorydata->visible = 1;
                
//                 $subcategory = \core_course_category::create($categorydata);
                
//                 $this->logger->info("Created period subcategory", [
//                     'name' => $periodName,
//                     'id' => $subcategory->id
//                 ]);
//             }
            
//             // Cache the result
//             $this->cache->set($cacheKey, $subcategory);
            
//             return $subcategory;
            
//         } catch (\Exception $e) {
//             $this->logger->error("Failed to create period subcategory", [
//                 'error' => $e->getMessage(),
//                 'period' => $periodName
//             ]);
//             return $parentCategory;
//         }
//     }
    
//     /**
//      * Determines period name based on event date and settings
//      */
//     private function determinePeriodName(\stdClass $event, CourseSettings $settings): string {
//         $startDate = strtotime($event->anlassDatumVon);
//         $year = date('y', $startDate);
//         $fullYear = date('Y', $startDate);
        
//         if ($settings->subcategorization) {
//             return $fullYear;
//         }
        
//         $month = (int)date('n', $startDate);
//         return ($month >= 8) ? "HS{$year}" : "FS{$year}";
//     }
// }

// /**
//  * Main task handler for scheduled synchronization
//  */
// class EventoCourseSyncTask extends \core\task\scheduled_task {
//     public function get_name(): string {
//         return get_string('taskname', 'local_eventocoursecreation');
//     }
    
//     public function execute() {
//         $trace = new \text_progress_trace();
//         $logger = new EventoLogger($trace);
        
//         try {
//             $container = new ServiceContainer($trace);
//             $syncService = $container->getCourseCreationService();
            
//             $result = $syncService->synchronizeAll();
            
//             if ($result === 0) {
//                 $logger->info("Synchronization completed successfully");
//             } else {
//                 $logger->error("Synchronization failed", ['result' => $result]);
//             }
            
//         } catch (\Exception $e) {
//             $logger->error("Task execution failed", ['error' => $e->getMessage()]);
//             throw $e;
//         }
//     }
// }

// /**
//  * Dependency injection container
//  */
// class ServiceContainer {
//     private array $services = [];
//     private \progress_trace $trace;
    
//     public function __construct(\progress_trace $trace) {
//         $this->trace = $trace;
//     }
    
//     public function getCourseCreationService(): CourseCreationService {
//         if (!isset($this->services[CourseCreationService::class])) {
//             $this->services[CourseCreationService::class] = new CourseCreationService(
//                 $this->getConfiguration(),
//                 $this->getCourseRepository(),
//                 $this->getTemplateManager(),
//                 $this->getEnrollmentManager(),
//                 $this->getLogger(),
//                 $this->getEventoService(),
//                 $this->getCategoryManager()
//             );
//         }
        
//         return $this->services[CourseCreationService::class];
//     }
    
//     private function getConfiguration(): EventoConfiguration {
//         if (!isset($this->services[EventoConfiguration::class])) {
//             $this->services[EventoConfiguration::class] = new EventoConfiguration();
//         }
//         return $this->services[EventoConfiguration::class];
//     }
    
//     private function getCourseRepository(): CourseRepository {
//         if (!isset($this->services[CourseRepository::class])) {
//             global $DB;
//             $this->services[CourseRepository::class] = new CourseRepository(
//                 $DB,
//                 $this->getCache(),
//                 $this->getLogger()
//             );
//         }
//         return $this->services[CourseRepository::class];
//     }
    
//     private function getTemplateManager(): TemplateManager {
//         if (!isset($this->services[TemplateManager::class])) {
//             $this->services[TemplateManager::class] = new TemplateManager(
//                 $this->getLogger(),
//                 $this->getCache()
//             );
//         }
//         return $this->services[TemplateManager::class];
//     }
    
//     private function getEnrollmentManager(): EnrollmentManager {
//         if (!isset($this->services[EnrollmentManager::class])) {
//             global $DB;
//             $this->services[EnrollmentManager::class] = new EnrollmentManager(
//                 $DB,
//                 $this->getLogger()
//             );
//         }
//         return $this->services[EnrollmentManager::class];
//     }
    
//     private function getLogger(): EventoLogger {
//         if (!isset($this->services[EventoLogger::class])) {
//             $this->services[EventoLogger::class] = new EventoLogger($this->trace);
//         }
//         return $this->services[EventoLogger::class];
//     }
    
//     private function getCache(): EventoCache {
//         if (!isset($this->services[EventoCache::class])) {
//             $this->services[EventoCache::class] = new EventoCache();
//         }
//         return $this->services[EventoCache::class];
//     }
    
//     private function getEventoService(): \local_evento_evento_service {
//         if (!isset($this->services[\local_evento_evento_service::class])) {
//             $this->services[\local_evento_evento_service::class] = new \local_evento_evento_service(
//                 null,
//                 null,
//                 $this->trace
//             );
//         }
//         return $this->services[\local_evento_evento_service::class];
//     }

//     private function getCategoryManager(): CategoryManager {
//         if (!isset($this->services[CategoryManager::class])) {
//             global $DB;
//             $this->services[CategoryManager::class] = new CategoryManager($DB, $this->getLogger(), $this->getCache());
//         }
//         return $this->services[CategoryManager::class];
//     }
// }