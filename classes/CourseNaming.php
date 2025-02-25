<?php

namespace local_eventocoursecreation;

require_once(__DIR__ . '/../locallib.php');

use stdClass;

/**
 * Handles course naming logic
 */
class CourseNaming
{
    /**
     * @var stdClass The Evento event data
     */
    private stdClass $event;

    /**
     * @var categorySettings The course settings
     */
    private EventoCategorySettings $categorySettings;

    /**
     * @var CourseRepository The course repository
     */
    private CourseRepository $courseRepository;

    /**
     * @var array The parsed naming components
     */
    private array $components;

    /**
     * @var EventoConfiguration The parsed naming components
     */
    private EventoConfiguration $eventoConfig;

    /**
     * Constructor
     *
     * @param stdClass                  $event
     * @param EventoCategorySettings    $categorySettings
     * @param CourseRepository          $courseRepository
     */
    public function __construct(
        stdClass $event,
        EventoCategorySettings $categorySettings,
        CourseRepository $courseRepository
    ) {
        $this->event = $event;
        $this->categorySettings = $categorySettings;
        $this->courseRepository = $courseRepository;
        $this->components = $this->parseComponents();
        $this->eventoConfig = EventoConfiguration::getInstance();

    }

    /**
     * Gets the long name for the course
     *
     * @return string
     */
    public function getLongName(): string
    {
        return $this->createName($this->components, true);
    }

    /**
     * Gets a unique short name for the course
     *
     * @return string
     */
    public function getUniqueShortName(): string
    {
        $baseName = $this->createName($this->components, false);
        return $this->makeUnique($baseName);
    }

    /**
     * Parses the naming components from the event data
     *
     * @return array
     */
    private function parseComponents(): array
    {
        $startTime = strtotime($this->event->anlassDatumVon);
        $moduleTokens = explode('.', $this->event->anlassNummer);
        $moduleIdentifier = $moduleTokens[1] ?? '';

        $parts = preg_split('/(?=[A-Z])/', $moduleIdentifier, -1, PREG_SPLIT_NO_EMPTY);
        $courseOfStudies = array_shift($parts) ?? '';
        $moduleAbr = implode('', $parts) ?: $moduleIdentifier;

        return [
            'period'         => $this->getPeriod($startTime),
            'moduleabr'      => $moduleAbr,
            'courseofstudies'=> $this->event->anlassVeranstalter ?: $courseOfStudies,
            'num'            => '',
            'name'           => $this->event->anlassBezeichnung
        ];
    }

    /**
     * Determines the period based on the event start time
     *
     * @param int $timestamp
     * @return string
     */
    private function getPeriod(int $timestamp): string
    {
        switch ($this->categorySettings->getSubCatOrganization()) {
            case 0:
                $returnVal = date('Ymd', $timestamp);
                break;
            case 1:
                $month = (int)date('n', $timestamp);
                $year = date('y', $timestamp);
                $returnVal = ($month >= 8) ? "HS{$year}" : "FS{$year}";
                break;
            case 2:
                $returnVal = date('Y', $timestamp);
                break;
        }
        
        return $returnVal;
    }

    /**
     * Creates the course name based on the template
     *
     * @param array $components
     * @param bool  $isLong
     * @return string
     */
    private function createName(array $components, bool $isLong): string {
        $template = $isLong ? 
            $this->eventoConfig->getCourseSettings()->getLongNameTemplate() : 
            $this->eventoConfig->getCourseSettings()->getShortNameTemplate();

        $replacements = [
            \EVENTOCOURSECREATION_NAME_PH_EVENTO_NAME => $components['name'],
            \EVENTOCOURSECREATION_NAME_PH_EVENTO_ABR  => $components['moduleabr'],
            \EVENTOCOURSECREATION_NAME_PH_PERIOD      => $components['period'],
            \EVENTOCOURSECREATION_NAME_PH_COS         => $components['courseofstudies'],
            \EVENTOCOURSECREATION_NAME_PH_NUM         => $components['num']
        ];

        return trim(str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        ));
    }

    /**
     * Ensures the short name is unique by appending a suffix if necessary
     *
     * @param string $baseName
     * @return string
     */
    private function makeUnique(string $baseName): string
    {
        $currentName = $baseName;
        $suffix = '';
        $attempt = 1;

        while ($this->courseRepository->findByShortName($currentName)) {
            $attempt++;
            $suffix = '_' . $attempt;
            $currentName = $baseName . $suffix;
        }

        return $currentName;
    }
}
