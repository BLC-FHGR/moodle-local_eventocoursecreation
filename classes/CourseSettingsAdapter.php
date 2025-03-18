<?php

namespace local_eventocoursecreation;

/**
 * Adapter to provide consistent interface between category settings and course settings
 */
class CourseSettingsAdapter {
    /**
     * @var EventoCategorySettings The category settings
     */
    private EventoCategorySettings $settings;
    
    /**
     * Constructor
     *
     * @param EventoCategorySettings $settings
     */
    public function __construct(EventoCategorySettings $settings) {
        $this->settings = $settings;
    }
    
    /**
     * @return bool
     */
    public function getCourseVisibility(): bool {
        return $this->settings->getCourseVisibility();
    }
    
    /**
     * @return int
     */
    public function getNewsItemsNumber(): int {
        return $this->settings->getNewsItemsNumber();
    }
    
    /**
     * @return int
     */
    public function getNumberOfSections(): int {
        return $this->settings->getNumberOfSections();
    }
    
    /**
     * @return bool
     */
    public function isTemplateCourseEnabled(): bool {
        return $this->settings->getEnableCourseTemplate();
    }
    
    /**
     * @return int|null
     */
    public function getTemplateCourse(): ?int {
        return $this->settings->getTemplateCourse();
    }
    
    /**
     * @return bool
     */
    public function getSetCustomCourseStartTime(): bool {
        return $this->settings->getSetCustomCourseStartTime();
    }
    
    /**
     * @return int
     */
    public function getStartTimeCourse(): int {
        return $this->settings->getStartTimeCourse();
    }
    
    /**
     * @return int
     */
    public function getSubCatOrganization(): int {
        return $this->settings->getSubCatOrganization();
    }
}