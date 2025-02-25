<?php

namespace local_eventocoursecreation;

use core_course_category;
use coding_exception;
use moodle_exception;

/**
 * Wrapper for course category functionality specific to Evento
 * 
 * This class extends Moodle's core functionality with Evento-specific
 * operations while maintaining proper integration with core APIs.
 */
class EventoCourseCategory {
    /** @var core_course_category The underlying Moodle category */
    private readonly core_course_category $category;
    
    /** @var EventoCategorySettings|null The Evento-specific settings */
    private readonly ?EventoCategorySettings $settings;

    /**
     * Private constructor to enforce factory method usage
     *
     * @param core_course_category $category The Moodle category
     * @param EventoCategorySettings|null $settings The Evento settings
     */
    private function __construct(
        core_course_category $category,
        ?EventoCategorySettings $settings
    ) {
        $this->category = $category;
        $this->settings = $settings;
    }

    /**
     * Get an EventoCourseCategory instance by ID
     *
     * @param int $categoryid The category ID
     * @return self
     * @throws moodle_exception If category doesn't exist
     */
    public static function get(int $categoryid): self {
        // Use Moodle's core API to get the category
        $category = core_course_category::get($categoryid, MUST_EXIST);
        
        // Get our Evento-specific settings
        $settings = EventoCategorySettings::getForCategory($categoryid);
        
        return new self($category, $settings);
    }

    /**
     * Check if this category allows Evento course creation
     *
     * @return bool
     */
    public function allowsEventoCourseCreation(): bool {
        // First check if category is visible and available
        if (!$this->category->is_uservisible()) {
            return false;
        }

        // Then check Evento-specific settings
        if ($this->settings === null) {
            return false;
        }

        return $this->settings->getEnableCatCourseCreation();
    }

    /**
     * Get module numbers from category idnumber
     *
     * @return array List of module numbers
     */
    public function getEventoModuleNumbers(): array {
        $idnumber = $this->category->idnumber;
        if (empty($idnumber)) {
            return [];
        }

        // Check if idnumber starts with the module prefix
        if (strpos($idnumber, EVENTOCOURSECREATION_IDNUMBER_PREFIX) !== 0) {
            return [];
        }

        // Parse module numbers from idnumber
        $parts = explode(EVENTOCOURSECREATION_IDNUMBER_DELIMITER, $idnumber);
        array_shift($parts); // Remove prefix
        return array_filter($parts, 'trim');
    }

    /**
     * Create a subcategory for a specific term
     *
     * @param string $name The category name
     * @param string $idnumber The category idnumber
     * @return self The new category
     */
    public function createTermSubcategory(string $name, string $idnumber): self {
        // Use core Moodle API to create category
        $data = [
            'name' => $name,
            'idnumber' => $idnumber,
            'parent' => $this->category->id,
            'visible' => $this->category->visible
        ];
        
        $newcategory = core_course_category::create($data);
        
        // Create default Evento settings for new category
        $settings = EventoCategorySettings::createForCategory(
            $newcategory->id,
            $this->settings ? $this->settings->toArray() : []
        );
        
        return new self($newcategory, $settings);
    }

    /**
     * Get subcategories that are managed by Evento
     *
     * @return array<self>
     */
    public function getEventoSubcategories(): array {
        $subcategories = $this->category->get_children();
        
        $eventocategories = [];
        foreach ($subcategories as $subcategory) {
            $settings = EventoCategorySettings::getForCategory($subcategory->id);
            if ($settings !== null) {
                $eventocategories[] = new self($subcategory, $settings);
            }
        }
        
        return $eventocategories;
    }

    // Delegate core functionality to the underlying category object
    
    public function getId(): int {
        return $this->category->id;
    }

    public function getName(): string {
        return $this->category->name;
    }

    public function getIdNumber(): string {
        return $this->category->idnumber;
    }

    public function isVisible(): bool {
        return $this->category->visible;
    }

    public function getPath(): string {
        return $this->category->path;
    }

    public function getDepth(): int {
        return $this->category->depth;
    }

    public function getEventoSettings(): ?EventoCategorySettings {
        return $this->settings;
    }
}