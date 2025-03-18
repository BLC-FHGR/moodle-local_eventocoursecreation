<?php

namespace local_eventocoursecreation;

use coding_exception;
use dml_exception;
use stdClass;

/**
 * Value object for evento category settings with database persistence
 */
class EventoCategorySettings {
    private const TABLE = 'eventocoursecreation';
    
    /**
     * @var array Cache for retrieved settings
     */
    private static array $instances = [];

    /**
     * Constructor is private, use factory methods to retrieve or create settings
     *
     * @param int $category Category ID
     * @param int|null $templateCourse Template course ID
     * @param bool $enableCourseTemplate Whether to use course template
     * @param bool $enableCatCourseCreation Whether course creation is enabled for category
     * @param int $startTimeSpringTermDay Spring term start day
     * @param int $startTimeSpringTermMonth Spring term start month
     * @param bool $execOnlyOnStartTimeSpringTerm Execute only on spring term start
     * @param int $startTimeAutumnTermDay Autumn term start day
     * @param int $startTimeAutumnTermMonth Autumn term start month
     * @param bool $execOnlyOnStartTimeAutumnTerm Execute only on autumn term start
     * @param bool $courseVisibility Default course visibility
     * @param int $newsItemsNumber Number of news items
     * @param int $numberOfSections Number of course sections
     * @param int $subCatOrganization Subcategory organization type
     * @param int $startTimeCourse Course start timestamp
     * @param bool $setCustomCourseStartTime Whether to use custom start time
     * @param int|null $id Record ID
     * @param int|null $timeModified Last modification timestamp
     */
    private function __construct(
        private readonly int $category,
        private readonly ?int $templateCourse,
        private readonly bool $enableCourseTemplate,
        private readonly bool $enableCatCourseCreation,
        private readonly int $startTimeSpringTermDay,
        private readonly int $startTimeSpringTermMonth,
        private readonly bool $execOnlyOnStartTimeSpringTerm,
        private readonly int $startTimeAutumnTermDay,
        private readonly int $startTimeAutumnTermMonth,
        private readonly bool $execOnlyOnStartTimeAutumnTerm,
        private readonly bool $courseVisibility,
        private readonly int $newsItemsNumber,
        private readonly int $numberOfSections,
        private readonly int $subCatOrganization,
        private readonly int $startTimeCourse,
        private readonly bool $setCustomCourseStartTime,
        private readonly ?int $id = null,
        private readonly ?int $timeModified = null
    ) {}

    /**
     * Get settings for a specific category
     *
     * @param int $categoryId Moodle category ID
     * @return self|null Settings object if found, null otherwise
     * @throws dml_exception
     */
    public static function getForCategory(int $categoryId): ?self {
        global $DB;

        if (isset(self::$instances[$categoryId])) {
            return self::$instances[$categoryId];
        }

        $record = $DB->get_record(self::TABLE, ['category' => $categoryId]);
        if (!$record) {
            return null;
        }

        self::$instances[$categoryId] = self::createFromRecord($record);
        return self::$instances[$categoryId];
    }

    /**
     * Create new settings for a category
     *
     * @param int $categoryId Moodle category ID
     * @param array|null $data Custom settings data (optional)
     * @return self New settings object
     * @throws coding_exception|dml_exception
     */
    public static function createForCategory(int $categoryId, ?array $data = null): self {
        global $DB;

        // Get defaults from global configuration
        $config = EventoConfiguration::getInstance();
        
        // Start with global defaults
        $record = new stdClass();
        $record->category = $categoryId;
        $record->templatecourse = $config->getCourseSettings()->getTemplateCourse();
        $record->enablecoursetemplate = $config->getCourseSettings()->isTemplateCourseEnabled();
        $record->enablecatcoursecreation = true; // Default to enabled
        
        // Term settings
        $termSettings = $config->getTermSettings();
        $record->starttimespringtermday = $termSettings->getSpringTermStartDay();
        $record->starttimespringtermmonth = $termSettings->getSpringTermStartMonth();
        $record->execonlyonstarttimespringterm = false; // Default to false
        $record->starttimeautumntermday = $termSettings->getAutumnTermStartDay();
        $record->starttimeautumntermmonth = $termSettings->getAutumnTermStartMonth();
        $record->execonlyonstarttimeautumnterm = false; // Default to false
        
        // Course settings
        $courseSettings = $config->getCourseSettings();
        $record->coursevisibility = $courseSettings->getCourseVisibility();
        $record->newsitemsnumber = $courseSettings->getNewsItemsNumber();
        $record->numberofsections = $courseSettings->getNumberOfSections();
        $record->subcatorganization = $courseSettings->getSubCatOrganization();
        $record->starttimecourse = $courseSettings->getStartTimeCourse();
        $record->setcustomcoursestarttime = $courseSettings->getSetCustomStartTime();
        $record->timemodified = time();
        
        // Override with custom data if provided
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                if (property_exists($record, $key)) {
                    $record->$key = $value;
                }
            }
        }

        $record->id = $DB->insert_record(self::TABLE, $record);
        
        $settings = self::createFromRecord($record);
        self::$instances[$categoryId] = $settings;
        
        return $settings;
    }

    /**
     * Update existing settings
     *
     * @param int $categoryId Category ID
     * @param array $data New settings data
     * @return self Updated settings object
     * @throws coding_exception|dml_exception
     */
    public static function updateForCategory(int $categoryId, array $data): self {
        global $DB;

        $existing = self::getForCategory($categoryId);
        if (!$existing) {
            throw new coding_exception("No settings exist for category: $categoryId");
        }

        $record = new stdClass();
        $record->id = $existing->getId();
        $record->category = $categoryId;
        $record->templatecourse = $data['templatecourse'] ?? $existing->getTemplateCourse();
        $record->enablecoursetemplate = $data['enablecoursetemplate'] ?? $existing->getEnableCourseTemplate();
        $record->enablecatcoursecreation = $data['enablecatcoursecreation'] ?? $existing->getEnableCatCourseCreation();
        $record->starttimespringtermday = $data['starttimespringtermday'] ?? $existing->getStartTimeSpringTermDay();
        $record->starttimespringtermmonth = $data['starttimespringtermmonth'] ?? $existing->getStartTimeSpringTermMonth();
        $record->execonlyonstarttimespringterm = $data['execonlyonstarttimespringterm'] ?? $existing->getExecOnlyOnStartTimeSpringTerm();
        $record->starttimeautumntermday = $data['starttimeautumntermday'] ?? $existing->getStartTimeAutumnTermDay();
        $record->starttimeautumntermmonth = $data['starttimeautumntermmonth'] ?? $existing->getStartTimeAutumnTermMonth();
        $record->execonlyonstarttimeautumnterm = $data['execonlyonstarttimeautumnterm'] ?? $existing->getExecOnlyOnStartTimeAutumnTerm();
        $record->coursevisibility = $data['coursevisibility'] ?? $existing->getCourseVisibility();
        $record->newsitemsnumber = $data['newsitemsnumber'] ?? $existing->getNewsItemsNumber();
        $record->numberofsections = $data['numberofsections'] ?? $existing->getNumberOfSections();
        $record->subcatorganization = $data['subcatorganization'] ?? $existing->getSubCatOrganization();
        $record->starttimecourse = $data['starttimecourse'] ?? $existing->getStartTimeCourse();
        $record->setcustomcoursestarttime = $data['setcustomcoursestarttime'] ?? $existing->getSetCustomCourseStartTime();
        $record->timemodified = time();

        $DB->update_record(self::TABLE, $record);
        
        $settings = self::createFromRecord($record);
        self::$instances[$categoryId] = $settings;
        
        return $settings;
    }

    /**
     * Create settings object from database record
     *
     * @param stdClass $record Database record
     * @return self
     */
    private static function createFromRecord(stdClass $record): self {
        return new self(
            (int)$record->category,
            $record->templatecourse ? (int)$record->templatecourse : null,
            (bool)$record->enablecoursetemplate,
            (bool)$record->enablecatcoursecreation,
            (int)$record->starttimespringtermday,
            (int)$record->starttimespringtermmonth,
            (bool)$record->execonlyonstarttimespringterm,
            (int)$record->starttimeautumntermday,
            (int)$record->starttimeautumntermmonth,
            (bool)$record->execonlyonstarttimeautumnterm,
            (bool)$record->coursevisibility,
            (int)$record->newsitemsnumber,
            (int)$record->numberofsections,
            (int)$record->subcatorganization,
            (int)$record->starttimecourse,
            (bool)$record->setcustomcoursestarttime,
            (int)$record->id,
            (int)$record->timemodified
        );
    }

    // Getter methods
    public function getId(): ?int {
        return $this->id;
    }

    public function getCategory(): int {
        return $this->category;
    }

    public function getTemplateCourse(): ?int {
        return $this->templateCourse;
    }

    public function getEnableCourseTemplate(): bool {
        return $this->enableCourseTemplate;
    }

    public function getEnableCatCourseCreation(): bool {
        return $this->enableCatCourseCreation;
    }

    public function getStartTimeSpringTermDay(): int {
        return $this->startTimeSpringTermDay;
    }

    public function getStartTimeSpringTermMonth(): int {
        return $this->startTimeSpringTermMonth;
    }

    public function getExecOnlyOnStartTimeSpringTerm(): bool {
        return $this->execOnlyOnStartTimeSpringTerm;
    }

    public function getStartTimeAutumnTermDay(): int {
        return $this->startTimeAutumnTermDay;
    }

    public function getStartTimeAutumnTermMonth(): int {
        return $this->startTimeAutumnTermMonth;
    }

    public function getExecOnlyOnStartTimeAutumnTerm(): bool {
        return $this->execOnlyOnStartTimeAutumnTerm;
    }

    public function getCourseVisibility(): bool {
        return $this->courseVisibility;
    }

    public function getNewsItemsNumber(): int {
        return $this->newsItemsNumber;
    }

    public function getNumberOfSections(): int {
        return $this->numberOfSections;
    }

    public function getSubCatOrganization(): int {
        return $this->subCatOrganization;
    }

    public function getStartTimeCourse(): int {
        return $this->startTimeCourse;
    }

    public function getSetCustomCourseStartTime(): bool {
        return $this->setCustomCourseStartTime;
    }

    public function getTimeModified(): ?int {
        return $this->timeModified;
    }

    /**
     * Convert settings to array format
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'templatecourse' => $this->templateCourse,
            'enablecoursetemplate' => $this->enableCourseTemplate,
            'enablecatcoursecreation' => $this->enableCatCourseCreation,
            'starttimespringtermday' => $this->startTimeSpringTermDay,
            'starttimespringtermmonth' => $this->startTimeSpringTermMonth,
            'execonlyonstarttimespringterm' => $this->execOnlyOnStartTimeSpringTerm,
            'starttimeautumntermday' => $this->startTimeAutumnTermDay,
            'starttimeautumntermmonth' => $this->startTimeAutumnTermMonth,
            'execonlyonstarttimeautumnterm' => $this->execOnlyOnStartTimeAutumnTerm,
            'coursevisibility' => $this->courseVisibility,
            'newsitemsnumber' => $this->newsItemsNumber,
            'numberofsections' => $this->numberOfSections,
            'subcatorganization' => $this->subCatOrganization,
            'starttimecourse' => $this->startTimeCourse,
            'setcustomcoursestarttime' => $this->setCustomCourseStartTime,
            'timemodified' => $this->timeModified
        ];
    }

    /**
     * Clear the cached instance (mainly for testing)
     */
    public static function clearInstance(): void {
        self::$instances = null;
    }
}