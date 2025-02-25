<?php

namespace local_eventocoursecreation\valueobjects;

/**
 * Value object for course-related settings
 */
class CourseSettings {
    /**
     * Constructor
     *
     * @param bool $courseVisibility Whether courses should be visible by default
     * @param int $newsItemsNumber Number of news items to show
     * @param int $numberOfSections Number of course sections
     * @param bool $enableCourseTemplate Whether to use course templates
     * @param int|null $templateCourse Template course ID if enabled
     * @param bool $setCustomCourseStartTime Whether to use custom start time
     * @param int $startTimeCourse Default course start time
     * @param int $defaultSubcatOrganization Default subcategory organization type
     * @param string $longNameTemplate Template for course long names
     * @param string $shortNameTemplate Template for course short names
     */
    public function __construct(
        private readonly bool $courseVisibility,
        private readonly int $newsItemsNumber,
        private readonly int $numberOfSections,
        private readonly bool $enableCourseTemplate,
        private readonly ?int $templateCourse,
        private readonly bool $setCustomCourseStartTime,
        private readonly int $startTimeCourse,
        private readonly int $defaultSubcatOrganization,
        private readonly string $longNameTemplate,
        private readonly string $shortNameTemplate
    ) {}

    /**
     * @return bool
     */
    public function getCourseVisibility(): bool {
        return $this->courseVisibility;
    }

    /**
     * @return int
     */
    public function getNewsItemsNumber(): int {
        return $this->newsItemsNumber;
    }

    /**
     * @return int
     */
    public function getNumberOfSections(): int {
        return $this->numberOfSections;
    }

    /**
     * @return bool
     */
    public function isTemplateCourseEnabled(): bool {
        return $this->enableCourseTemplate;
    }

    /**
     * @return int|null
     */
    public function getTemplateCourse(): ?int {
        return $this->templateCourse;
    }

    /**
     * @return bool
     */
    public function hasCustomStartTime(): bool {
        return $this->setCustomCourseStartTime;
    }

    /**
     * @return int
     */
    public function getStartTimeCourse(): int {
        return $this->startTimeCourse;
    }

    /**
     * @return int
     */
    public function getDefaultSubcatOrganization(): int {
        return $this->defaultSubcatOrganization;
    }

    /**
     * @return string
     */
    public function getLongNameTemplate(): string {
        return $this->longNameTemplate;
    }

    /**
     * @return string
     */
    public function getShortNameTemplate(): string {
        return $this->shortNameTemplate;
    }

    /**
     * Convert settings to array format
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'coursevisibility' => $this->courseVisibility,
            'newsitemsnumber' => $this->newsItemsNumber,
            'numberofsections' => $this->numberOfSections,
            'enablecoursetemplate' => $this->enableCourseTemplate,
            'templatecourse' => $this->templateCourse,
            'setcustomcoursestarttime' => $this->setCustomCourseStartTime,
            'starttimecourse' => $this->startTimeCourse,
            'defaultsubcatorganization' => $this->defaultSubcatOrganization,
            'longcoursenaming' => $this->longNameTemplate,
            'shortcoursenaming' => $this->shortNameTemplate
        ];
    }
}