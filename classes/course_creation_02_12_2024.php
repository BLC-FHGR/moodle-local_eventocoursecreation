<?php
/**
 * Evento course creation plugin
 * 
 * Handles automatic course creation and management based on Evento system data.
 * Runs as a scheduled task to sync courses between Evento and Moodle.
 *
 * @package    local_eventocoursecreation
 * @copyright  2024 FHGR Julien Rädler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/evento/classes/evento_service.php');
require_once($CFG->libdir . '/weblib.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

class local_eventocoursecreation_course_creation {
    // Core dependencies
    private $config;
    protected $trace;
    protected $eventoservice;
    protected $enrolplugin;

    // Data caches
    protected $moodlecourses = array();
    protected $mainevents = array();
    protected $subcourseenrolments = array();
    protected $categoryVeranstalterMap = array();
    protected $currentSubcategory = null;
    protected $currentPeriodName = null;
    protected $checking_event_date = null;

    /**
     * Constructor
     */
    public function __construct() {
        $this->config = get_config('local_eventocoursecreation');
        $this->trace = new \null_progress_trace();
        $this->eventoservice = new local_evento_evento_service(null, null, $this->trace);
        $this->enrolplugin = enrol_get_plugin('evento');
    }

    /**
     * Set the progress trace instance
     *
     * @param \progress_trace $trace Progress tracking instance
     */
    public function set_trace(\progress_trace $trace): void {
        $this->trace = $trace;
        $this->eventoservice->set_trace($trace);
    }

    /**
     * Main synchronization method for course creation
     * 
     * @param \progress_trace $trace Progress tracking
     * @param int|null $categoryid Optional specific category to process
     * @param bool $force Whether to force sync regardless of settings
     * @return int Status code (0=success, 1=error, 2=preconditions not met)
     */
    public function course_sync(\progress_trace $trace, $categoryid = null, $force = false) {
        try {
            $this->trace = $trace;
            $this->moodlecourses = array();
            $this->currentSubcategory = null;
            $this->currentPeriodName = null;

            // Raise execution limits
            core_php_time_limit::raise();
            raise_memory_limit(MEMORY_HUGE);

            // Check if the plugin is enabled
            if (!$this->config->enableplugin) {
                $this->trace->output("Plugin not enabled");
                return 2;
            }

            $this->trace->output('Starting Evento course synchronization...');

            // Initialize Evento service connection
            if (!$this->eventoservice->init_call()) {
                $this->trace->output("Could not initialize Evento connection");
                return 2;
            }

            try {
                // Get active Veranstalter from Evento
                $veranstalterList = $this->eventoservice->get_active_veranstalter();
                if (empty($veranstalterList)) {
                    $this->trace->output("No active Veranstalter found");
                    return 2;
                }

                // Process categories
                if ($categoryid) {
                    $categories = $this->get_single_category($veranstalterList, $categoryid);
                    $this->trace->output("Processing single category: " . $categoryid);
                } else {
                    $categories = $this->process_all_categories($veranstalterList);
                    $this->trace->output("Processing all categories");
                }

                // Process each category
                foreach ($categories as $category) {
                    $this->trace->output("Processing category: {$category->id} ({$category->name})");

                    // Use category settings or default settings
                    $setting = local_eventocoursecreation_setting::get($category->id) ?? $this->get_default_setting();

                    // Get associated Veranstalter
                    $veranstalter = $this->categoryVeranstalterMap[$category->idnumber];

                    // Retrieve events for the Veranstalter
                    try {
                        $events = $this->eventoservice->get_events_by_veranstalter_years($veranstalter->IDBenutzer);
                    } catch (Exception $e) {
                        $this->trace->output("Error retrieving events for Veranstalter ID {$veranstalter->IDBenutzer}: " . $e->getMessage());
                        continue;
                    }

                    // Process each event
                    foreach ($events as $event) {
                        try {
                            $this->process_event($event, $category, $setting, $force);
                        } catch (Exception $e) {
                            $this->trace->output("Error processing event {$event->anlassNummer}: " . $e->getMessage());
                        }
                    }
                }

                // Handle remaining sub-events
                $this->process_remaining_subcourses();

                $this->trace->output('Evento course synchronization finished successfully');
                $this->trace->finished();
                return 0;

            } catch (Exception $e) {
                $this->trace->output("Error during synchronization: " . $e->getMessage());
                return 1;
            }
        } catch (Exception $e) {
            debugging("Fatal error: " . $e->getMessage());
            $this->trace->output('... Evento course synchronization aborted unexpectedly');
            $this->trace->finished();
            return 1;
        }
    }

    /**
     * Provides default settings when category-specific settings are not available
     * 
     * @return stdClass Default settings object
     */
    private function get_default_setting() {
        $setting = new stdClass();
        $config = get_config('local_eventocoursecreation');

        // Enable course creation by default
        $setting->enablecatcoursecreation = isset($config->enablecatcoursecreation) ? $config->enablecatcoursecreation : 1;

        // Use global settings or default values
        $setting->enablecoursetemplate = isset($config->enablecoursetemplate) ? $config->enablecoursetemplate : 0;
        $setting->templatecourse = isset($config->templatecourse) ? $config->templatecourse : null;

        $setting->setcustomcoursestarttime = isset($config->setcustomcoursestarttime) ? $config->setcustomcoursestarttime : 0;
        $setting->starttimecourse = isset($config->starttimecourse) ? $config->starttimecourse : null;

        $setting->coursevisibility = isset($config->coursevisibility) ? $config->coursevisibility : 1; // Visible

        $setting->newsitemsnumber = isset($config->newsitemsnumber) ? $config->newsitemsnumber : 5;

        $setting->numberofsections = isset($config->numberofsections) ? $config->numberofsections : 10;

        $setting->useyearcategories = isset($config->useyearcategories) ? $config->useyearcategories : 0;

        // Default start times for terms
        $setting->starttimespringtermday = isset($config->starttimespringtermday) ? $config->starttimespringtermday : 1;
        $setting->starttimespringtermmonth = isset($config->starttimespringtermmonth) ? $config->starttimespringtermmonth : 2; // February

        $setting->starttimeautumntermday = isset($config->starttimeautumntermday) ? $config->starttimeautumntermday : 1;
        $setting->starttimeautumntermmonth = isset($config->starttimeautumntermmonth) ? $config->starttimeautumntermmonth : 8; // August

        return $setting;
    }

    /**
     * Process a single event for course creation.
     *
     * @param object $event The event to process.
     * @param object $category The category context.
     * @param object $setting Category settings.
     * @param bool $force Whether to force creation.
     */
    protected function process_event($event, $category, $setting, $force = false) {
        $now = time();

        // Check if event has both start and end dates.
        if (empty($event->anlassDatumVon) || empty($event->anlassDatumBis)) {
            $this->trace->output("Event {$event->idAnlass} skipped: Missing start or end date.");
            return;
        }

        // Convert dates to timestamps.
        $startDate = strtotime($event->anlassDatumVon);
        $endDate = strtotime($event->anlassDatumBis);

        // Check if end date is in the past.
        if ($endDate < $now) {
            $this->trace->output("Event {$event->idAnlass} skipped: End date is in the past.");
            return;
        }

        $this->checking_event_date = $startDate;
        $this->trace->output("\nProcessing event:");
        $this->trace->output("- Number: " . $event->anlassNummer);
        $this->trace->output("- Name: " . $event->anlassBezeichnung);
        $this->trace->output("- Start date: " . $event->anlassDatumVon);
        $this->trace->output("- End date: " . $event->anlassDatumBis);

        try {
            // Handle sub-events.
            if (isset($event->anlass_Zusatz15) && $event->anlass_Zusatz15 !== $event->idAnlass) {
                $this->handle_sub_event($event);
                return;
            }

            // Check if course already exists.
            $existingCourse = $this->get_existing_course($event->idAnlass, $category->id, $event->anlassNummer);
            if ($existingCourse) {
                $this->trace->output("Course already exists (ID: {$existingCourse->id})");
                return;
            }

            // Verify creation conditions.
            if (!$force) {
                if ($setting->enablecatcoursecreation != 1) {
                    $this->trace->output("Course creation disabled for category");
                    return;
                }

                if (!$this->is_creation_allowed($setting)) {
                    $this->trace->output("Course creation not allowed at this time");
                    return;
                }
            }

            // Attempt course creation.
            $course = $this->create_new_course($event, $category, $setting, $force);
            if ($course) {
                $this->trace->output("Successfully created course ID: {$course->id}");
                $this->setup_course_enrollments($course, $event);
            } else {
                $this->trace->output("Failed to create course");
            }
        } finally {
            $this->checking_event_date = null;
        }
    }

    /**
     * Process a single category and its events
     */
    // In process_category():
    protected function process_category($category, $force) {
        $this->currentSubcategory = null;
        $this->currentPeriodName = null;

        $setting = local_eventocoursecreation_setting::get($category->id);
        if (!$force && ($setting->enablecatcoursecreation != 1 || !$this->is_creation_allowed($setting))) {
            return;
        }

        $events = $this->get_category_events($category);
        $this->trace->output("Got " . count($events) . " events for category");
        
        foreach ($events as $event) {
            $this->trace->output("\nProcessing event:");
            $this->trace->output("- Number: " . $event->anlassNummer);
            $this->trace->output("- Name: " . $event->anlassBezeichnung);
            $this->trace->output("- Start date: " . $event->anlassDatumVon);
            $this->trace->output("- Status: " . $event->anlassStatus->statusName);
            try {
                $this->process_event($event, $category, $setting);
            } catch (Exception $e) {
                $this->trace->output("Error processing event: " . $e->getMessage());
            }
        }
    }

    /**
     * Processes all categories by creating only necessary categories based on events
     * 
     * @param array $veranstalterList List of Veranstalter objects from Evento
     * @return array Array of Moodle category objects
     */
    protected function process_all_categories($veranstalterList) {
        global $DB;
        $this->trace->output("\n=== Processing All Categories ===");

        // Map each Veranstalter by ID
        $veranstalterMap = array();
        foreach ($veranstalterList as $ver) {
            $veranstalterMap[$ver->IDBenutzer] = $ver;
        }

        // Array to hold IDs of Veranstalter needing categories
        $requiredVeranstalterIds = array();

        // Determine which Veranstalter have events that will create courses
        foreach ($veranstalterList as $ver) {
            try {
                $events = $this->eventoservice->get_events_by_veranstalter_years($ver->IDBenutzer);
                foreach ($events as $event) {
                    if ($this->willEventCreateCourse($event)) {
                        // Mark this Veranstalter and its parents
                        $this->markVeranstalterAndParents($ver->IDBenutzer, $veranstalterMap, $requiredVeranstalterIds);
                        break; // No need to check more events for this Veranstalter
                    }
                }
            } catch (Exception $e) {
                $this->trace->output("Error retrieving events for Veranstalter ID {$ver->IDBenutzer}: " . $e->getMessage());
            }
        }

        // Create categories in Moodle
        $categoryMap = array(); // Key: Veranstalter ID, Value: Moodle category ID
        foreach ($requiredVeranstalterIds as $verId => $value) {
            $ver = $veranstalterMap[$verId];
            $parentId = $ver->OE;
            $parentCategoryId = isset($categoryMap[$parentId]) ? $categoryMap[$parentId] : 0; // Root category if no parent
            $categoryId = $this->ensureCategoryExists($ver, $parentCategoryId);
            $categoryMap[$verId] = $categoryId;
        }

        // Prepare categories for processing
        $categories = array();
        foreach ($categoryMap as $verId => $categoryId) {
            $category = core_course_category::get($categoryId, IGNORE_MISSING);
            if ($category) {
                $categories[$category->id] = $category;
                // Map the category to the Veranstalter
                $this->categoryVeranstalterMap[$category->idnumber] = $veranstalterMap[$verId];
            }
        }

        return $categories;
    }

    /**
     * Recursively mark a Veranstalter and all its parents as required
     */
    private function markVeranstalterAndParents($verId, $veranstalterMap, &$requiredVeranstalterIds) {
        if (isset($requiredVeranstalterIds[$verId])) {
            // Already marked
            return;
        }
        $requiredVeranstalterIds[$verId] = true;
        $ver = $veranstalterMap[$verId];
        $parentId = $ver->OE;
        if ($parentId && isset($veranstalterMap[$parentId])) {
            $this->markVeranstalterAndParents($parentId, $veranstalterMap, $requiredVeranstalterIds);
        }
    }

    /**
     * Ensure a category exists in Moodle for the given Veranstalter
     */
    private function ensureCategoryExists($veranstalter, $parentCategoryId) {
        global $DB;
        // Check if category already exists
        $existing = $DB->get_record('course_categories', ['idnumber' => $veranstalter->IDBenutzer]);
        if ($existing) {
            return $existing->id;
        }
        // Create a new category
        $categorydata = new stdClass();
        $categorydata->name = $veranstalter->benutzerName;
        $categorydata->idnumber = $veranstalter->IDBenutzer;
        $categorydata->parent = $parentCategoryId;
        $categorydata->visible = 1;
        $category = core_course_category::create($categorydata);
        $this->trace->output("Created category '{$categorydata->name}' with ID {$category->id}");
        return $category->id;
    }


    /**
     * Handle processing of sub-events (parallel courses)
     */
    protected function handle_sub_event($event) {
        // Check if main course exists first
        if (isset($event->anlass_Zusatz15)) {
            $mainCourse = $this->get_existing_course($event->anlass_Zusatz15, null, null);
            if ($mainCourse) {
                $this->create_enrollment_instance($mainCourse, $event);
                $this->trace->output("Added enrollment for sub-event {$event->anlassNummer}");
                return;
            }
        }
        
        // Queue for later if main course not found
        $this->subcourseenrolments[] = $event;
        $this->trace->output("Queued sub-event {$event->anlassNummer} for later processing");
    }

    /**
     * Process any remaining queued sub-events after main sync
     */
    protected function process_remaining_subcourses() {
        if (empty($this->subcourseenrolments)) {
            return;
        }

        $this->trace->output('Processing remaining queued sub-events...');
        
        foreach ($this->subcourseenrolments as $subevent) {
            if (isset($subevent->anlass_Zusatz15)) {
                $mainCourse = $this->get_existing_course($subevent->anlass_Zusatz15, null, null);
                if ($mainCourse) {
                    $this->create_enrollment_instance($mainCourse, $subevent);
                    $this->trace->output("Added enrollment for queued sub-event {$subevent->anlassNummer}");
                } else {
                    $this->trace->output("Warning: Could not find main course for sub-event {$subevent->anlassNummer}");
                }
            }
        }
    }

    /**
     * Get a single category and its mapping
     * 
     * @param array $veranstalter Array of veranstalter objects 
     * @param int $categoryid The category ID to fetch
     * @return array The processed category array
     */
    protected function get_single_category($veranstalter, $categoryid) {
        global $DB;
        
        $categories = array();
        $this->categoryVeranstalterMap = array();
        
        try {
            $category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
            
            if (empty($category->idnumber)) {
                $this->trace->output("Warning: Category {$categoryid} has no idnumber set");
                return array();
            }
            
            // Find matching Veranstalter in hierarchy
            foreach ($veranstalter as $ver) {
                if ($ver->IDBenutzer === $category->idnumber) {
                    $this->categoryVeranstalterMap[$category->idnumber] = $ver;
                    $categories[$category->id] = $category;
                    $this->trace->output("Mapped category '{$category->name}' to Veranstalter '{$ver->benutzerVorname}'");
                    break;
                }
            }
            
        } catch (Exception $e) {
            $this->trace->output("Error processing category {$categoryid}: " . $e->getMessage());
        }
        
        return $categories;
    }

    /**
     * Get or create subcategory for event periods
     * 
     * @param object $event The evento event
     * @param object $category Parent category
     * @param object $setting Category settings
     * @return object The target category
     */
    protected function get_period_subcategory($event, $category, $setting) {
        global $DB;
        
        if (empty($event->anlassDatumVon)) {
            return $category;
        }

        $periodName = $this->determine_period_name($event, $setting);
        
        if ($this->currentSubcategory && $this->currentPeriodName === $periodName) {
            return $this->currentSubcategory;
        }
        
        $subcategory = $DB->get_record('course_categories', array(
            'parent' => $category->id,
            'name' => $periodName
        ));
        
        if (!$subcategory) {
            try {
                $categorydata = new stdClass();
                $categorydata->name = $periodName;
                $categorydata->idnumber = $category->idnumber . '_' . $periodName;
                $categorydata->parent = $category->id;
                $categorydata->visible = 1;
                
                $subcategory = core_course_category::create($categorydata);
                $this->trace->output("Created new period subcategory: {$periodName}");
            } catch (Exception $e) {
                $this->trace->output("Failed to create period subcategory {$periodName}: " . $e->getMessage());
                return $category;
            }
        }
        
        $this->currentSubcategory = $subcategory;
        $this->currentPeriodName = $periodName;
        
        return $subcategory;
    }

    /**
     * Determine period name based on event date and settings
     */
    protected function determine_period_name($event, $setting) {
        $startDate = strtotime($event->anlassDatumVon);
        $year = date('y', $startDate);
        $fullYear = date('Y', $startDate);
        
        if (!empty($setting->useyearcategories)) {
            return $fullYear;
        }
        
        $month = (int)date('n', $startDate);
        return ($month >= 8) ? "HS{$year}" : "FS{$year}";
    }

    /**
     * Get events for a specific category
     */
    protected function get_category_events($category) {
        $this->trace->output("\n=== Getting events for category ===");
        $this->trace->output("Category ID: {$category->id}");
        $this->trace->output("Category idnumber: {$category->idnumber}");
        
        // Debug the categoryVeranstalterMap
        $this->trace->output("\nVeranstalter Map contents:");
        foreach ($this->categoryVeranstalterMap as $key => $ver) {
            $this->trace->output("Key: $key => Veranstalter: {$ver->IDBenutzer}");
        }
        
        if (isset($this->categoryVeranstalterMap[$category->idnumber])) {
            $ver = $this->categoryVeranstalterMap[$category->idnumber];
            $this->trace->output("\nFound veranstalter:");
            $this->trace->output("IDBenutzer: {$ver->IDBenutzer}");
            $this->trace->output("Name: {$ver->benutzerName}");
            
            try {
                $events = $this->eventoservice->get_events_by_veranstalter_years($ver->IDBenutzer);
                $this->trace->output("\nGot " . count($events) . " events");
                return $events;
            } catch (Exception $e) {
                $this->trace->output("Error: " . $e->getMessage());
                return array();
            }
        } else {
            $this->trace->output("\nNO VERANSTALTER FOUND IN MAP for {$category->idnumber}");
        }
        return array();
    }

    /**
     * Determine if an event should create a course.
     *
     * @param object $event The event object from Evento.
     * @return bool True if the event should create a course, false otherwise.
     */
    public function willEventCreateCourse($event): bool {
        $now = time();

        // Check if event has both start and end dates.
        if (empty($event->anlassDatumVon) || empty($event->anlassDatumBis)) {
            $this->trace->output("Event {$event->idAnlass} skipped: Missing start or end date.");
            return false;
        }

        // Convert dates to timestamps.
        $startDate = strtotime($event->anlassDatumVon);
        $endDate = strtotime($event->anlassDatumBis);

        // Check if end date is in the past.
        if ($endDate < $now) {
            $this->trace->output("Event {$event->idAnlass} skipped: End date is in the past.");
            return false;
        }

        // Check if course already exists.
        if ($this->get_existing_course($event->idAnlass)) {
            return false;
        }

        // Check if the plugin is enabled.
        if (!$this->config->enableplugin) {
            return false;
        }

        // Since the category may not exist yet, use default settings for timing.
        $defaultSettings = $this->get_default_setting();

        // Check if creation is allowed based on default settings.
        if (!$this->is_creation_allowed($defaultSettings)) {
            return false;
        }

        return true;
    }

    /**
     * Create a new course from an evento event
     */
    protected function create_new_course($event, $category, $setting, $force = false) {
        global $CFG, $DB;
    
        try {
            $targetCategory = $this->get_period_subcategory($event, $category, $setting);
    
            $naming = new local_eventocoursecreation_course_naming(
                $event->anlassBezeichnung,
                $event->anlassNummer,
                strtotime($event->anlassDatumVon),
                $event->idAnlass,
                $event->anlassVeranstalter
            );
    
            $newcourse = new stdClass();
            $newcourse->idnumber = (string)$event->idAnlass;
            $newcourse->fullname = $naming->create_long_course_name();
            $newcourse->shortname = $naming->create_short_course_name();

            if (empty($newcourse->shortname)) {
                $this->trace->output("Cannot create course because a unique shortname could not be generated without conflict.");
                return null;
            }
    
            // Check if a course with the same shortname and enrollment exists
            if ($this->course_with_same_shortname_and_enrollment_exists($newcourse->shortname, $event->idAnlass)) {
                $this->trace->output("Course with shortname {$newcourse->shortname} and associated enrollment from the same Evento event already exists. Skipping creation.");
                return null;
            }
    
            $newcourse->category = $targetCategory->id;
    
            $this->set_course_dates($newcourse, $event, $setting);
            $this->set_course_settings($newcourse, $setting);
    
            require_once($CFG->dirroot . "/course/lib.php");
    
            $course = create_course($newcourse);
            $this->moodlecourses[$event->idAnlass] = $course;
    
            if ($setting->enablecoursetemplate == 1 && !empty($setting->templatecourse)) {
                $this->restore_template_content($course, $setting->templatecourse);
            }
    
            $this->trace->output("Created new course: {$course->shortname}");
            return $course;
    
        } catch (Exception $e) {
            $this->trace->output("Error creating course for event {$event->anlassNummer}: " . $e->getMessage());
            return null;
        }
    }
    

    /**
     * Set the start and end dates for a course
     */
    protected function set_course_dates($course, $event, $setting) {
        if (!empty($event->anlassDatumVon)) {
            $course->startdate = strtotime($event->anlassDatumVon);
        }
        if (!empty($event->anlassDatumBis)) {
            $course->enddate = strtotime($event->anlassDatumBis);
        }
        
        if (!empty($setting->setcustomcoursestarttime)) {
            $course->startdate = $setting->starttimecourse;
        }
    }

    /**
     * Set course visibility and other settings
     */
    protected function set_course_settings($course, $setting) {
        $course->visible = $setting->coursevisibility;
        $course->newsitems = $setting->newsitemsnumber;
        $course->numsections = $setting->numberofsections;
    }

    /**
     * Setup enrollments for a course
     */
    protected function setup_course_enrollments($course, $event) {
        global $DB;
        
        try {
            // Process any pending sub-events
            foreach ($this->subcourseenrolments as $key => $subcourse) {
                if ($subcourse->anlass_Zusatz15 === $event->idAnlass) {
                    $params = [
                        'courseid' => $course->id,
                        'customint4' => $subcourse->idAnlass,
                        'enrol' => 'evento'
                    ];
                    if (!$DB->record_exists('enrol', $params)) {
                        $this->create_enrollment_instance($course, $subcourse);
                    }
                    unset($this->subcourseenrolments[$key]);
                }
            }

            // Create main enrollment if needed
            $params = [
                'courseid' => $course->id,
                'customint4' => $event->idAnlass,
                'enrol' => 'evento'
            ];
            if (!$DB->record_exists('enrol', $params)) {
                $this->create_enrollment_instance($course, $event);
            }

        } catch (Exception $e) {
            $this->trace->output("Error setting up enrollments: " . $e->getMessage());
        }
    }

    /**
     * Create an enrollment instance for an event
     */
    protected function create_enrollment_instance($course, $event) {
        $fields = $this->enrolplugin->get_instance_defaults();
        $fields['customint4'] = $event->idAnlass;
        $fields['customtext1'] = $event->anlassNummer;

        if (isset($event->anlass_Zusatz15) && $event->anlass_Zusatz15 !== $event->idAnlass) {
            $fields['name'] = 'Evento Parallelanlass';
        }

        if ($this->enrolplugin->add_instance($course, $fields)) {
            $this->trace->output("Added enrollment for event {$event->anlassNummer}");
            return true;
        }

        return false;
    }

    /**
     * Get existing course by evento ID, searching through the category hierarchy
     * 
     * @param int $eventoid The evento ID to search for
     * @param int|null $categoryid The top-level category ID to search under (optional)
     * @param string|null $anlassNummer The evento event number (for logging)
     * @return object|false Course record if found, false otherwise
     */
    protected function get_existing_course($eventoid, $categoryid = null, $anlassNummer = null) {
        global $DB;
        
        if ($categoryid) {
            $this->trace->output("Category ID type: " . gettype($categoryid));
            
            // Get the category and its subcategories
            $category = core_course_category::get((int)$categoryid);
            $subcategories = $category->get_children();
            
            // Build array of category IDs including parent, ensuring all are integers
            $categoryIds = array((int)$categoryid);
            foreach ($subcategories as $subcat) {
                $categoryIds[] = (int)$subcat->id;
            }
            
            // Debug output
            $this->trace->output("Category IDs: " . print_r($categoryIds, true));
            
            // Build category path condition
            list($catsql, $catparams) = $DB->get_in_or_equal($categoryIds, SQL_PARAMS_QM);
            
            // Add idnumber as first parameter since we're using question marks
            array_unshift($catparams, (string)$eventoid);
            
            $sql = "SELECT c.* 
                    FROM {course} c
                    WHERE c.idnumber = ? 
                    AND c.category $catsql";
            
            $this->trace->output("Executing SQL with params: " . print_r($catparams, true));
            $course = $DB->get_record_sql($sql, $catparams);
        } else {
            $course = $DB->get_record('course', array('idnumber' => (string)$eventoid));
        }
        
        if ($course) {
            $this->trace->output("Found course id {$course->id} in category {$course->category}");
        } else {
            $this->trace->output("No course found for evento ID $eventoid" . 
                ($anlassNummer ? " (anlassNummer: $anlassNummer)" : ""));
        }
        
        return $course ?: false;
    }

    protected function course_with_same_shortname_and_enrollment_exists($shortname, $eventoid) {
        global $DB;
    
        // Check if a course with the same shortname exists
        $course = $DB->get_record('course', array('shortname' => $shortname), '*', IGNORE_MULTIPLE);
        if ($course) {
            // Check if the course has an enrollment from the same Evento event
            $enrolInstances = $DB->get_records('enrol', array(
                'courseid' => $course->id,
                'enrol' => 'evento',
                'customint4' => $eventoid
            ));
            if (!empty($enrolInstances)) {
                return true;
            }
        }
        return false;
    }    

    /**
     * Restore template content to a new course
     * 
     * @param object $course Course to restore content to
     * @param int $templateid ID of template course
     */
    protected function restore_template_content($course, $templateid) {
        global $CFG, $DB, $USER;
        
        try {
            require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
            require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
            
            // Get backup directory
            $backupdir = $this->get_restore_content_dir($templateid);
            if (!$backupdir) {
                $this->trace->output("Could not get restore content directory for template {$templateid}");
                return false;
            }
            
            // Setup restore controller
            $tempdir = make_backup_temp_directory($backupdir);
            $rc = new restore_controller(
                $backupdir,
                $course->id,
                backup::INTERACTIVE_NO,
                backup::MODE_GENERAL,
                $USER->id,
                backup::TARGET_EXISTING_ADDING
            );

            // Configure restore settings
            $rc->get_plan()->get_setting('users')->set_value(false);
            $rc->get_plan()->get_setting('user_files')->set_value(false);
            if ($rc->get_plan()->setting_exists('role_assignments')) {
                $rc->get_plan()->get_setting('role_assignments')->set_value(false);
            }
            
            // Execute restore
            if ($rc->execute_precheck()) {
                $rc->execute_plan();
                $this->trace->output("Successfully restored template content to course {$course->shortname}");
            } else {
                $this->trace->output("Precheck failed for template restore to course {$course->shortname}");
            }
            
            $rc->destroy();
            return true;
            
        } catch (Exception $e) {
            $this->trace->output("Error restoring template content: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the directory of the object to restore.
     *
     * @param int $templatecourse id of a course, which is used to get the content
     * @return string|false|null subdirectory in $CFG->backuptempdir/..., false when an error occured
     *                           and null when there is simply nothing.
     */
    protected function get_restore_content_dir($templatecourse) {
        if (empty($templatecourse) || !is_numeric($templatecourse)) {
            return null;
        }
        
        $errors = array();
        $dir = self::create_restore_content_dir($templatecourse, $errors);
        
        if (!empty($errors)) {
            foreach ($errors as $key => $message) {
                debugging("Error during get content of course id {$templatecourse}; ". $message);
                $this->trace->output("Error during get content of course id {$templatecourse}; ". $message);
            }
            return false;
        }
        
        return ($dir === false) ? null : $dir;
    }

    /**
     * Get the restore content tempdir.
     *
     * The tempdir is the sub directory in which the backup has been extracted.
     *
     * This caches the result for better performance, but $CFG->keeptempdirectoriesonbackup
     * needs to be enabled, otherwise the cache is ignored.
     *
     * @param int $templatecourse id of a course.
     * @param array $errors will be populated with errors found.
     * @return string|false false when the backup couldn't retrieved.
     */
    public static function create_restore_content_dir($templatecourse = null, &$errors = array()) {
        global $CFG, $DB, $USER;
        
        $cachekey = !empty($templatecourse) && is_numeric($templatecourse) ? 'backup_id:' . $templatecourse : null;
        if (empty($cachekey)) {
            return false;
        }
        
        $usecache = !empty($CFG->keeptempdirectoriesonbackup);
        $cache = $usecache ? cache::make('local_eventocoursecreation', 'coursecreation') : null;
        
        // Check cache or create new backup
        if (!$usecache || 
            ($backupid = $cache->get($cachekey)) === false || 
            !is_dir(get_backup_temp_directory($backupid))) {
                
            $backupid = null;
            $courseid = $DB->get_field('course', 'id', array('id' => $templatecourse), IGNORE_MISSING);
            
            if (!empty($courseid)) {
                try {
                    $bc = new backup_controller(
                        backup::TYPE_1COURSE,
                        $courseid,
                        backup::FORMAT_MOODLE,
                        backup::INTERACTIVE_NO,
                        backup::MODE_IMPORT,
                        $USER->id
                    );
                    $bc->execute_plan();
                    $backupid = $bc->get_backupid();
                    $bc->destroy();
                } catch (Exception $e) {
                    debugging('Error creating backup: ' . $e->getMessage());
                    $errors['backuperror'] = $e->getMessage();
                    return false;
                }
            } else {
                $errors['coursetorestorefromdoesnotexist'] = new lang_string(
                    'coursetorestorefromdoesnotexist',
                    'local_eventocoursecreation'
                );
            }
            
            if ($usecache && $backupid) {
                $cache->set($cachekey, $backupid);
            }
        }
        
        return $backupid ?? false;
    }

    /**
     * Check if course creation is allowed based on term schedule settings
     *
     * @param local_eventocoursecreation_setting $setting Category-level settings
     * @return bool True if creation should proceed
     */
    public function is_creation_allowed($setting, $force = false) {
        if ($force) {
            return true;
        }
        
        $now = $this->get_midnight_timestamp();
        $this->trace->output("Checking if current time is in creation period");
    
        // Check spring term
        $spring = $this->get_term_period(
            $setting->starttimespringtermday, 
            $setting->starttimespringtermmonth,
            $this->config->endtimespringtermday,
            $this->config->endtimespringtermmonth,
            $now
        );
    
        if ($this->is_in_term_period($now, $spring)) {
            $this->trace->output("Yes - in spring creation period");
            return true;
        }
    
        // Check autumn term
        $autumn = $this->get_term_period(
            $setting->starttimeautumntermday,
            $setting->starttimeautumntermmonth,
            $this->config->endtimeautumntermday,
            $this->config->endtimeautumntermmonth,
            $now
        );
    
        if ($this->is_in_term_period($now, $autumn)) {
            $this->trace->output("Yes - in autumn creation period");
            return true;
        }
    
        $this->trace->output("No - not in any creation period");
        return false;
    }

    /**
     * Get midnight timestamp for current day
     * 
     * @return int Unix timestamp
     */
    public function get_midnight_timestamp() {
        $now = time();
        return mktime(0, 0, 0, date("m", $now), date("d", $now), date("Y", $now));
    }

    /**
     * Calculate start and end timestamps for a term
     * 
     * @param int $startDay Term start day
     * @param int $startMonth Term start month
     * @param int $endDay Term end day
     * @param int $endMonth Term end month
     * @param int $now Current timestamp
     * @return array Term period ['start' => timestamp, 'end' => timestamp]
     */
    public function get_term_period($startDay, $startMonth, $endDay, $endMonth, $now) {
        // Get the year of the current timestamp
        $currentYear = (int)date('Y', $now);
        
        // If we're checking an event in a future year, use that year instead
        $eventYear = $currentYear;
        if (func_num_args() > 5) {
            $eventDate = func_get_arg(5);
            if ($eventDate) {
                $eventYear = (int)date('Y', $eventDate);
                $this->trace->output("Adjusting term period for future event in year: $eventYear");
            }
        }
        
        $start = mktime(0, 0, 0, $startMonth, $startDay, $eventYear);
        $end = mktime(0, 0, 0, $endMonth, $endDay, $eventYear);
    
        // If the term spans across years (e.g., autumn term)
        if ($start > $end) {
            if ($now < $start) {
                $start = mktime(0, 0, 0, $startMonth, $startDay, $eventYear - 1);
            } else {
                $end = mktime(0, 0, 0, $endMonth, $endDay, $eventYear + 1);
            }
        }
    
        $this->trace->output("Calculated term period for year $eventYear:");
        $this->trace->output("- Start: " . date('Y-m-d H:i:s', $start));
        $this->trace->output("- End: " . date('Y-m-d H:i:s', $end));
    
        return array('start' => $start, 'end' => $end);
    }

    /**
     * Check if current time falls within term period
     */
    public function is_in_term_period($now, $term) {
        return ($now >= $term['start'] && $now <= $term['end']);
    }

    /**
     * Check if execution should proceed based on timing rules
     */
    public function should_execute($now, $termStart, $execOnlyOnStart) {
        return $execOnlyOnStart ? ($now === $termStart) : true;
    }

    /**
     * Generate preview data for potential course creation
     * 
     * @param int $categoryid The category to preview
     * @return array Preview data for potential courses
     */
    public function get_preview_courses(int $categoryid): array {
        if (empty($this->trace)) {
            $this->trace = new \null_progress_trace();
        }

        if (empty($categoryid)) {
            throw new moodle_exception('invalidcategoryid', 'local_eventocoursecreation');
        }

        try {
            // Check prerequisites
            if (!$this->config->enableplugin || !$this->eventoservice->init_call()) {
                $this->trace->output('Prerequisites not met for preview generation');
                return array();
            }

            // Get active veranstalter
            $veranstalter = $this->eventoservice->get_active_veranstalter();
            if (empty($veranstalter)) {
                $this->trace->output('No active veranstalter found');
                return array();
            }
            
            // Get category data
            $categories = $this->get_single_category($veranstalter, $categoryid);
            if (empty($categories)) {
                $this->trace->output('No valid category found for preview');
                return array();
            }
            
            $category = reset($categories);
            $setting = local_eventocoursecreation_setting::get($category->id);
            if (!$setting) {
                $this->trace->output('No settings found for category');
                return array();
            }

            // Get events and generate preview data
            $events = $this->get_category_events($category);
            if (empty($events)) {
                $this->trace->output('No events found for category');
                return array();
            }

            return $this->generate_preview_data($events, $category, $setting);

        } catch (Exception $e) {
            $this->trace->output("Error generating preview: " . $e->getMessage());
            debugging('Error in get_preview_courses: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Generate formatted preview data for events
     */
    private function generate_preview_data($events, $category, $setting) {
        $previewData = array();
        
        foreach ($events as $event) {
            // Skip sub-events
            if (isset($event->anlass_Zusatz15) && $event->anlass_Zusatz15 !== $event->idAnlass) {
                continue;
            }

            try {
                if ($this->get_existing_course($event->idAnlass)) {
                    continue;
                }

                $targetCategory = $this->get_period_subcategory($event, $category, $setting);
                $naming = new local_eventocoursecreation_course_naming(
                    $event->anlassBezeichnung,
                    $event->anlassNummer,
                    strtotime($event->anlassDatumVon),
                    null,
                    $event->anlassVeranstalter
                );

                $previewData[] = array(
                    'eventId' => $event->idAnlass,
                    'eventNumber' => $event->anlassNummer,
                    'name' => $naming->create_long_course_name(),
                    'shortname' => $naming->create_short_course_name(),
                    'categoryname' => $targetCategory->name,
                    'startdate' => strtotime($event->anlassDatumVon),
                    'enddate' => strtotime($event->anlassDatumBis),
                    'settings' => array(
                        'visibility' => $setting->coursevisibility,
                        'newsitems' => $setting->newsitemsnumber,
                        'numsections' => $setting->numberofsections,
                        'usetemplate' => ($setting->enablecoursetemplate == 1 && 
                                        !empty($setting->templatecourse))
                    ),
                    'subcourses' => $this->get_preview_subcourses($event, $events),
                    'canCreate' => $setting->enablecatcoursecreation == 1 && 
                                 $this->is_creation_allowed($setting)
                );

            } catch (Exception $e) {
                $this->trace->output("Error processing preview for event {$event->anlassNummer}: " . 
                                   $e->getMessage());
                continue;
            }
        }

        // Sort by start date
        usort($previewData, function($a, $b) {
            return $a['startdate'] - $b['startdate'];
        });

        return $previewData;
    }

    /**
     * Get preview data for sub-events
     */
    private function get_preview_subcourses($mainEvent, $allEvents) {
        $subcourses = array();
        
        foreach ($allEvents as $event) {
            if (isset($event->anlass_Zusatz15) && 
                $event->anlass_Zusatz15 === $mainEvent->idAnlass && 
                $event->idAnlass !== $mainEvent->idAnlass) {
                    
                $subcourses[] = array(
                    'eventId' => $event->idAnlass,
                    'eventNumber' => $event->anlassNummer,
                    'name' => $event->anlassBezeichnung,
                    'startdate' => strtotime($event->anlassDatumVon),
                    'enddate' => strtotime($event->anlassDatumBis)
                );
            }
        }
        
        return $subcourses;
    }
    
    /**
     * Create a single course based on an event ID.
     *
     * @param int $eventid The ID of the event to create a course for.
     * @param int $categoryid The ID of the category to place the course in.
     * @param bool $force Whether to force creation regardless of settings.
     * @param array|null $cachedEvents Optional cached events data.
     * @return bool True on success, false on failure.
     */
    public function create_single_course($eventid, $categoryid, $force = false, $cachedEvents = null) {
        global $DB;
    
        try {
            // Get the category.
            $category = $DB->get_record('course_categories', array('id' => $categoryid), '*', MUST_EXIST);
    
            // Get the setting for the category.
            $setting = local_eventocoursecreation_setting::get($category->id);
    
            // Check if creation is allowed unless force is true.
            if (!$force && ($setting->enablecatcoursecreation != 1 || !$this->is_creation_allowed($setting))) {
                $this->trace->output("Course creation not allowed for category {$category->name}");
                return false;
            }
    
            // Get the event data.
            if ($cachedEvents && isset($cachedEvents[$eventid])) {
                $event = (object)$cachedEvents[$eventid];
            } else {
                // Fetch the event from the Evento service.
                $event = $this->eventoservice->get_event_by_id($eventid);
                if (!$event) {
                    $this->trace->output("Event with ID {$eventid} not found");
                    return false;
                }
            }
    
            // Process the event.
            $this->process_event($event, $category, $setting, $force);
    
            return true;
    
        } catch (Exception $e) {
            $this->trace->output("Error creating course for event ID {$eventid}: " . $e->getMessage());
            return false;
        }
    }    

}

class CategoryHierarchyProcessor {
    private $trace;
    private $eventoService;
    private $courseCreator;
    private $cache = [];
    
    public function __construct($trace, $eventoService, $courseCreator) {
        $this->trace = $trace;
        $this->eventoService = $eventoService;
        $this->courseCreator = $courseCreator;
    }

    /**
     * Evaluate Veranstalter and their events to determine minimum necessary category structure
     */
    public function evaluateRequiredCategories($veranstalter) {
        $requiredCategories = [];
        $veranstalterMap = [];
        
        // Build map for parent lookups
        foreach ($veranstalter as $ver) {
            $veranstalterMap[$ver->IDBenutzer] = $ver;
        }
        
        // First pass - identify categories that directly need courses
        foreach ($veranstalter as $ver) {
            $events = $this->eventoService->get_events_by_veranstalter_years($ver->IDBenutzer);
            
            if ($this->hasEventsRequiringCourses($events)) {
                $requiredCategories[$ver->IDBenutzer] = [
                    'veranstalter' => $ver,
                    'hasDirectCourses' => true,
                    'parentRequired' => false
                ];
                
                // Mark all parents as required
                $this->markParentCategoriesRequired($ver, $veranstalterMap, $requiredCategories);
            }
        }
        
        return $requiredCategories;
    }
    
    /**
     * Check if any events will create courses based on global settings
     */
    private function hasEventsRequiringCourses($events) {
        if (empty($events)) {
            return false;
        }
        
        foreach ($events as $event) {
            if ($this->courseCreator->willEventCreateCourse($event)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Mark all parent categories as required for hierarchy
     */
    private function markParentCategoriesRequired($veranstalter, $veranstalterMap, &$requiredCategories) {
        $current = $veranstalter;
        
        while (!empty($current->OE) && isset($veranstalterMap[$current->OE])) {
            $parentId = $current->OE;
            
            if (!isset($requiredCategories[$parentId])) {
                $requiredCategories[$parentId] = [
                    'veranstalter' => $veranstalterMap[$parentId],
                    'hasDirectCourses' => false,
                    'parentRequired' => true
                ];
            }
            
            $current = $veranstalterMap[$parentId];
        }
    }
    
    /**
     * Create minimal category hierarchy based on requirements
     */
    public function createMinimalHierarchy($requiredCategories) {
        global $DB;
        
        $categoryMap = [];
        
        // First handle root categories (no parent)
        foreach ($requiredCategories as $id => $data) {
            if (empty($data['veranstalter']->OE)) {
                $categoryMap[$id] = $this->ensureCategoryExists($data['veranstalter'], 0);
            }
        }
        
        // Then process remaining categories in parent-child order
        $remaining = array_filter($requiredCategories, function($data) {
            return !empty($data['veranstalter']->OE);
        });
        
        while (!empty($remaining)) {
            foreach ($remaining as $id => $data) {
                $parentId = $data['veranstalter']->OE;
                
                if (isset($categoryMap[$parentId])) {
                    $categoryMap[$id] = $this->ensureCategoryExists(
                        $data['veranstalter'], 
                        $categoryMap[$parentId]
                    );
                    unset($remaining[$id]);
                }
            }
        }
        
        return $categoryMap;
    }
    
    /**
     * Create or get existing category
     */
    private function ensureCategoryExists($veranstalter, $parentId) {
        global $DB;
        
        // Check cache first
        $cacheKey = $veranstalter->IDBenutzer;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        // Check if category already exists
        $existing = $DB->get_record('course_categories', [
            'idnumber' => $veranstalter->IDBenutzer
        ]);
        
        if ($existing) {
            $this->cache[$cacheKey] = $existing->id;
            return $existing->id;
        }
        
        // Create new category
        $categorydata = new stdClass();
        $categorydata->name = $veranstalter->benutzerName;
        $categorydata->idnumber = $veranstalter->IDBenutzer;
        $categorydata->parent = $parentId;
        $categorydata->visible = 1;
        
        $category = core_course_category::create($categorydata);
        
        $this->trace->output("Created category '{$categorydata->name}' with ID {$category->id}");
        
        $this->cache[$cacheKey] = $category->id;
        return $category->id;
    }
}

/**
 * Helper class for handling course naming based on Evento data
 */
class local_eventocoursecreation_course_naming {
    private $eventolongname;
    private $eventomodulenumber;
    private $naming_components;
    private $config;
    private $eventoid;

    /**
     * Initialize course naming with evento data
     * 
     * @param string $eventolongname Full event name from Evento
     * @param string $eventomodulenumber Module number from Evento
     * @param int|null $modulstarttime Optional start time for period calculation
     * @param int|null $eventoid Evento ID for enrollment checking
     * @param string|null $veranstalter Veranstalter identifier
     */
    public function __construct($eventolongname, $eventomodulenumber, $modulstarttime = null, $eventoid = null, $veranstalter = null) {
        $this->config = get_config('local_eventocoursecreation');
        $this->eventolongname = $eventolongname;
        $this->eventomodulenumber = $eventomodulenumber;
        $this->eventoid = $eventoid;
        $this->parse_module_components($modulstarttime, $veranstalter);
    }

    /**
     * Parse all module components needed for naming
     */
    private function parse_module_components($modulstarttime, $veranstalter) {
        // Split eventomodulenumber into tokens by '.'
        $modtokens = explode('.', $this->eventomodulenumber);

        // Get module identifier (second token)
        $moduleIdentifier = $modtokens[1] ?? '';

        // Initialize courseofstudies and moduleabr
        $courseofstudies = '';
        $moduleabr = '';

        if ($moduleIdentifier !== '') {
            // Split moduleIdentifier at uppercase letters
            $parts = preg_split('/(?=[A-Z])/', $moduleIdentifier, -1, PREG_SPLIT_NO_EMPTY);
            $courseofstudies = array_shift($parts) ?? '';
            $moduleabr = implode('', $parts) ?: $this->eventolongname;
        }

        $this->naming_components = array(
            'period' => $this->extract_period($modulstarttime),
            'moduleabr' => $moduleabr,
            'courseofstudies' => $veranstalter ?: $courseofstudies,
            'num' => '',
            'name' => $this->eventolongname
        );
    }


    /**
     * Extract period based on module start time
     */
    private function extract_period($modulstarttime) {
        if ($modulstarttime) {
            $month = date('n', $modulstarttime);
            $year = date('y', $modulstarttime);
            return (in_array($month, [2,3,4,5,6,7]) ? 'FS' : 'HS') . $year;
        }
        return '';
    }

    /**
     * Create the long (full) name for the course
     */
    public function create_long_course_name() {
        return trim($this->create_name($this->config->longcoursenaming));
    }

    /**
     * Create the short name for the course
     * 
     * @param int $existingCourseId ID of course being updated, if any
     * @return string Short course name
     */
    public function create_short_course_name($existingCourseId = null) {
        $baseName = trim($this->create_name($this->config->shortcoursenaming));
        return $this->ensure_unique_shortname($baseName, $existingCourseId);
    }

    /**
     * Ensure a course shortname is unique
     * 
     * @param string $shortname Base shortname to check
     * @param int $existingCourseId ID of course being updated, if any
     * @return string Unique shortname
     */
    private function ensure_unique_shortname($shortname, $existingCourseId = null) {
        global $DB;
    
        $suffix = '';
        $attempt = 1;
        $currentName = $shortname;
    
        do {
            $params = array('shortname' => $currentName);
            if ($existingCourseId) {
                $params['id'] = array('!=', $existingCourseId);
            }
    
            // Check if course exists
            $existingCourse = $DB->get_record('course', $params);
            if (!$existingCourse) {
                break;
            }
    
            // Check if existing course uses same Evento event enrollment
            if ($this->eventoid && $this->has_same_enrollment($existingCourse->id)) {
                // Since a course with the same shortname and enrollment exists, we should not create a new one
                $currentName = ''; // Force the loop to exit
                break;
            }
    
            // Add incrementing suffix
            $attempt++;
            $suffix = '_' . $attempt;
            $currentName = $shortname . $suffix;
    
        } while (true);
    
        return $currentName;
    }    

    /**
     * Check if a course uses the same evento enrollment
     * 
     * @param int $courseid Course ID to check
     * @return bool True if course has same evento enrollment
     */
    private function has_same_enrollment($courseid) {
        global $DB;
        
        $enrolInstances = $DB->get_records('enrol', array(
            'courseid' => $courseid,
            'enrol' => 'evento'
        ));
        
        foreach ($enrolInstances as $enrol) {
            if ($enrol->customint4 == $this->eventoid) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Create a course name using template and available components
     */
    private function create_name($template) {
        $replacements = array(
            EVENTOCOURSECREATION_NAME_PH_EVENTO_NAME => $this->naming_components['name'],
            EVENTOCOURSECREATION_NAME_PH_EVENTO_ABR => $this->naming_components['moduleabr'],
            EVENTOCOURSECREATION_NAME_PH_PERIOD => $this->naming_components['period'],
            EVENTOCOURSECREATION_NAME_PH_COS => $this->naming_components['courseofstudies'],
            EVENTOCOURSECREATION_NAME_PH_NUM => $this->naming_components['num']
        );
        
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }
}
