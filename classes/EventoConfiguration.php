<?php

namespace local_eventocoursecreation;

use coding_exception;
use dml_exception;
use local_eventocoursecreation\valueobjects\CourseSettings;
use local_eventocoursecreation\valueobjects\TermSettings;

/**
 * Value object for global Evento plugin settings
 */
class EventoConfiguration {
    /**
     * @var self|null Cached instance for singleton pattern
     */
    private static ?self $instance = null;

    /**
     * Constructor is private to enforce singleton pattern through factory method
     *
     * @param bool $enablePlugin Whether plugin is enabled
     * @param string $longNameTemplate Template for course long names
     * @param string $shortNameTemplate Template for course short names
     * @param int $subcatOrganization Default subcategory organization type
     * @param bool $courseVisibility Default course visibility
     * @param int $newsItemsNumber Default number of news items
     * @param int $numberOfSections Default number of course sections
     * @param bool $enableCourseTemplate Whether to use course templates by default
     * @param int|null $templateCourse Default template course ID
     * @param bool $setCustomCourseStartTime Whether to use custom start time
     * @param int $startTimeCourse Default course start time
     * @param int $springTermStartDay Spring term start day
     * @param int $springTermStartMonth Spring term start month
     * @param int $springTermEndDay Spring term end day
     * @param int $springTermEndMonth Spring term end month
     * @param int $autumnTermStartDay Autumn term start day
     * @param int $autumnTermStartMonth Autumn term start month
     * @param int $autumnTermEndDay Autumn term end day
     * @param int $autumnTermEndMonth Autumn term end month
     */
    private function __construct(
        private readonly bool $enablePlugin,
        private readonly string $longNameTemplate,
        private readonly string $shortNameTemplate,
        private readonly int $subcatOrganization,
        private readonly bool $courseVisibility,
        private readonly int $newsItemsNumber,
        private readonly int $numberOfSections,
        private readonly bool $enableCourseTemplate,
        private readonly ?int $templateCourse,
        private readonly bool $setCustomCourseStartTime,
        private readonly int $startTimeCourse,
        private readonly int $springTermStartDay,
        private readonly int $springTermStartMonth,
        private readonly int $springTermEndDay,
        private readonly int $springTermEndMonth,
        private readonly int $autumnTermStartDay,
        private readonly int $autumnTermStartMonth,
        private readonly int $autumnTermEndDay,
        private readonly int $autumnTermEndMonth
    ) {}

    /**
     * Get the singleton instance of the configuration
     *
     * @return self
     * @throws dml_exception
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = self::loadFromDatabase();
        }
        return self::$instance;
    }

    /**
     * Load configuration from database with fallback to defaults
     *
     * @return self
     * @throws dml_exception
     */
    private static function loadFromDatabase(): self {
        $config = get_config('local_eventocoursecreation');

        return new self(
            enablePlugin: !empty($config->enableplugin),
            longNameTemplate: $config->longcoursenaming ?? EVENTOCOURSECREATION_NAME_LONGNAME,
            shortNameTemplate: $config->shortcoursenaming ?? EVENTOCOURSECREATION_NAME_SHORTNAME,
            subcatOrganization: (int)($config->subcatorganization ?? EVENTOCOURSECREATION_SUBCAT_SEMESTER),
            courseVisibility: !empty($config->coursevisibility),
            newsItemsNumber: (int)($config->newsitemsnumber ?? 5),
            numberOfSections: (int)($config->numberofsections ?? 10),
            enableCourseTemplate: !empty($config->enablecoursetemplate),
            templateCourse: !empty($config->templatecourse) ? (int)$config->templatecourse : null,
            setCustomCourseStartTime: !empty($config->setcustomcoursestarttime),
            startTimeCourse: (int)($config->starttimecourse ?? EVENTOCOURSECREATION_DEFAULT_CUSTOM_START),
            springTermStartDay: (int)($config->starttimespringtermday ?? EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_STARTDAY),
            springTermStartMonth: (int)($config->starttimespringtermmonth ?? EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_STARTMONTH),
            springTermEndDay: (int)($config->endtimespringtermday ?? EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_ENDDAY),
            springTermEndMonth: (int)($config->endtimespringtermmonth ?? EVENTOCOURSECREATION_DEFAULT_SPRINGTERM_ENDMONTH),
            autumnTermStartDay: (int)($config->starttimeautumntermday ?? EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_STARTDAY),
            autumnTermStartMonth: (int)($config->starttimeautumntermmonth ?? EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_STARTMONTH),
            autumnTermEndDay: (int)($config->endtimeautumntermday ?? EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_ENDDAY),
            autumnTermEndMonth: (int)($config->endtimeautumntermmonth ?? EVENTOCOURSECREATION_DEFAULT_AUTUMNTERM_ENDMONTH)
        );
    }

    /**
     * Clear the cached instance (mainly for testing)
     */
    public static function clearInstance(): void {
        self::$instance = null;
    }

    /**
     * Get course-related settings as a value object
     *
     * @return CourseSettings
     */
    public function getCourseSettings(): CourseSettings {
        return new CourseSettings(
            $this->courseVisibility,
            $this->newsItemsNumber,
            $this->numberOfSections,
            $this->enableCourseTemplate,
            $this->templateCourse,
            $this->setCustomCourseStartTime,
            $this->startTimeCourse,
            $this->subcatOrganization,
            $this->longNameTemplate,
            $this->shortNameTemplate
        );
    }

    /**
     * Get term-related settings as a value object
     *
     * @return TermSettings
     */
    public function getTermSettings(): TermSettings {
        return new TermSettings(
            $this->springTermStartDay,
            $this->springTermStartMonth,
            $this->springTermEndDay,
            $this->springTermEndMonth,
            $this->autumnTermStartDay,
            $this->autumnTermStartMonth,
            $this->autumnTermEndDay,
            $this->autumnTermEndMonth
        );
    }

    public function isPluginEnabled(): bool {
        return $this->enablePlugin;
    }

    /**
     * Convert configuration to array format
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'enableplugin' => $this->enablePlugin,
            'longcoursenaming' => $this->longNameTemplate,
            'shortcoursenaming' => $this->shortNameTemplate,
            'subcatorganization' => $this->subcatOrganization,
            'coursevisibility' => $this->courseVisibility,
            'newsitemsnumber' => $this->newsItemsNumber,
            'numberofsections' => $this->numberOfSections,
            'enablecoursetemplate' => $this->enableCourseTemplate,
            'templatecourse' => $this->templateCourse,
            'setcustomcoursestarttime' => $this->setCustomCourseStartTime,
            'starttimecourse' => $this->startTimeCourse,
            'starttimespringtermday' => $this->springTermStartDay,
            'starttimespringtermmonth' => $this->springTermStartMonth,
            'endtimespringtermday' => $this->springTermEndDay,
            'endtimespringtermmonth' => $this->springTermEndMonth,
            'starttimeautumntermday' => $this->autumnTermStartDay,
            'starttimeautumntermmonth' => $this->autumnTermStartMonth,
            'endtimeautumntermday' => $this->autumnTermEndDay,
            'endtimeautumntermmonth' => $this->autumnTermEndMonth
        ];
    }
}