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

    /**
     * Constructor
     */
    public function __construct() {
        $this->config = get_config('local_eventocoursecreation');
        $this->trace = new \null_progress_trace();
        $this->eventoservice = new local_evento_evento_service();
        $this->enrolplugin = enrol_get_plugin('evento');
    }

    /**
     * Set the progress trace instance
     *
     * @param \progress_trace $trace Progress tracking instance
     */
    public function set_trace(\progress_trace $trace): void {
        $this->trace = $trace;
    }

    /**
     * Main synchronization method for course creation
     * 
     * @param progress_trace $trace Progress tracking
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
            
            core_php_time_limit::raise();
            raise_memory_limit(MEMORY_HUGE);

            if (!$this->config->enableplugin) {
                $this->trace->output("Plugin not enabled");
                return 2;
            }

            $this->trace->output('Starting evento course synchronisation...');

            // Initialize connection using evento service
            if (!$this->eventoservice->init_call()) {
                $this->trace->output("Could not initialize Evento connection");
                return 2;
            }

            try {
                // Get active veranstalter using evento service
                $veranstalter = $this->eventoservice->get_active_veranstalter();
                print_r($veranstalter);
                if (empty($veranstalter)) {
                    $this->trace->output("No active Veranstalter found");
                    return 2;
                }
                
                // Process categories
                if ($categoryid) {
                    $categories = $this->get_single_category($veranstalter, $categoryid);
                } else {
                    $categories = $this->process_all_categories($veranstalter);
                }
                
                // Process each category
                foreach ($categories as $category) {
                    $this->process_category($category, $force);
                }

                // Handle remaining sub-events
                $this->process_remaining_subcourses();
                
            } catch (Exception $e) {
                $this->trace->output("Error during synchronization: " . $e->getMessage());
                return 1;
            }
            
            $this->trace->output('Evento course synchronisation finished successfully');
            $this->trace->finished();
            return 0;

        } catch (Exception $e) {
            debugging("Fatal error: " . $e->getMessage());
            $this->trace->output('... evento course synchronisation aborted unexpected');
            $this->trace->finished();
            return 1;
        }
    }

    /**
     * Process a single category and its events
     */
    protected function process_category($category, $force) {
        $this->currentSubcategory = null;
        $this->currentPeriodName = null;

        $setting = local_eventocoursecreation_setting::get($category->id);
        if (!$force && ($setting->enablecatcoursecreation != 1 || !$this->is_creation_allowed($setting))) {
            return;
        }

        $events = $this->get_category_events($category);
        foreach ($events as $event) {
            try {
                $this->process_event($event, $category, $setting);
            } catch (Exception $e) {
                $this->trace->output("Error processing event {$event->anlassNummer}: " . $e->getMessage());
            }
        }
    }

    /**
     * Process a single event for course creation
     *
     * @param object $event The event to process
     * @param object $category The category context
     * @param object $setting Category settings
     */
    protected function process_event($event, $category, $setting, $force = false) {
        // Existing initialization code...
    
        // Check if this is a main event or sub-event
        if (isset($event->anlass_Zusatz15) && $event->anlass_Zusatz15 !== $event->idAnlass) {
            $this->handle_sub_event($event);
            return;
        }
    
        // Check if the course already exists.
        $course = $this->get_existing_course($event->idAnlass, $category->id, $event->anlassNummer);
        if ($course) {
            $this->trace->output("Course already exists for event {$event->anlassNummer}. Skipping.");
            return;
        }
    
        // Create a new course.
        $course = $this->create_new_course($event, $category, $setting, $force);
    
        if ($course) {
            $this->setup_course_enrollments($course, $event);
        }
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
            
            foreach ($veranstalter as $ver) {
                if (!empty($ver->benutzerVorname) && $ver->benutzerVorname === $category->idnumber) {
                    $this->categoryVeranstalterMap[$ver->benutzerVorname] = $ver;
                    $categories[$category->id] = $category;
                    $this->trace->output("Mapped category '{$category->name}' to OE '{$ver->benutzerVorname}'");
                    break;
                }
            }
            
            if (empty($categories)) {
                $this->trace->output("Warning: No matching Veranstalter found for category {$category->name}");
            }
            
        } catch (Exception $e) {
            $this->trace->output("Error processing category {$categoryid}: " . $e->getMessage());
        }
        
        return $categories;
    }

    /**
     * Process all categories and map to veranstalter
     * 
     * @param array $veranstalter Array of veranstalter objects
     * @return array Mapped categories
     */
    protected function process_all_categories($veranstalter) {
        global $DB;
        
        $categories = array();
        $this->categoryVeranstalterMap = array();
        
        $existingCategories = $DB->get_records('course_categories', 
            array('parent' => 0), 
            '', 
            'id, idnumber, name'
        );

        $processedOEs = array();
        
        foreach ($veranstalter as $ver) {
            if (empty($ver->benutzerVorname) || isset($processedOEs[$ver->benutzerVorname])) {
                continue;
            }
            
            $processedOEs[$ver->benutzerVorname] = true;
            
            // Look for existing category
            foreach ($existingCategories as $category) {
                if (!empty($category->idnumber) && $category->idnumber === $ver->benutzerVorname) {
                    $categories[$category->id] = $category;
                    $this->categoryVeranstalterMap[$ver->benutzerVorname] = $ver;
                    break;
                }
            }
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
        if (isset($this->categoryVeranstalterMap[$category->idnumber])) {
            $ver = $this->categoryVeranstalterMap[$category->idnumber];
            try {
                return $this->eventoservice->get_events_by_veranstalter_years($ver->benutzerVorname);
            } catch (Exception $e) {
                $this->trace->output("Error retrieving events for category {$category->name}: " . $e->getMessage());
                return array();
            }
        }
        return array();
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
     * Get existing course by evento ID
     */
    protected function get_existing_course($eventoid, $categoryid = null, $anlassNummer = null) {
        global $DB;
        
        $params = array('idnumber' => (string)$eventoid);
        if ($categoryid) {
            $params['category'] = $categoryid;
        }
        
        $this->trace->output("Searching with params: " . json_encode($params));
        $course = $DB->get_record('course', $params);
        $this->trace->output("Result: " . ($course ? "Found course id {$course->id}" : "No course found"));
        
        return $course;
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

        // Check spring term first
        $spring = $this->get_term_period(
            $setting->starttimespringtermday, 
            $setting->starttimespringtermmonth,
            $this->config->endtimespringtermday,
            $this->config->endtimespringtermmonth,
            $now
        );

        if ($this->is_in_term_period($now, $spring)) {
            return $this->should_execute($now, $spring['start'], $setting->execonlyonstarttimespringterm);
        }

        // If not in spring term, check autumn term
        $autumn = $this->get_term_period(
            $setting->starttimeautumntermday,
            $setting->starttimeautumntermmonth,
            $this->config->endtimeautumntermday,
            $this->config->endtimeautumntermmonth,
            $now
        );

        if ($this->is_in_term_period($now, $autumn)) {
            return $this->should_execute($now, $autumn['start'], $setting->execonlyonstarttimeautumnterm);
        }

        return false;
    }

    /**
     * Get midnight timestamp for current day
     * 
     * @return int Unix timestamp
     */
    private function get_midnight_timestamp() {
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
    private function get_term_period($startDay, $startMonth, $endDay, $endMonth, $now) {
        $year = (int)date("Y", $now);
        
        $start = mktime(0, 0, 0, $startMonth, $startDay, $year);
        $end = mktime(0, 0, 0, $endMonth, $endDay, $year);

        // Adjust years if term spans calendar year boundary
        if ($start > $end) {
            if ($now < $start) {
                $start = mktime(0, 0, 0, $startMonth, $startDay, $year - 1);
            } else {
                $end = mktime(0, 0, 0, $endMonth, $endDay, $year + 1);
            }
        }

        return array('start' => $start, 'end' => $end);
    }

    /**
     * Check if current time falls within term period
     */
    private function is_in_term_period($now, $term) {
        return ($now >= $term['start'] && $now <= $term['end']);
    }

    /**
     * Check if execution should proceed based on timing rules
     */
    private function should_execute($now, $termStart, $execOnlyOnStart) {
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
