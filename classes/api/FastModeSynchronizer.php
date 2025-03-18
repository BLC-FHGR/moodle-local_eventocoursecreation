<?php

namespace local_eventocoursecreation\api;

use DateTime;
use local_evento_evento_service;
use local_eventocoursecreation\EventoLogger;
use moodle_exception;

/**
 * Fast mode implementation for Evento course creation
 *
 * This is an optimized approach that only processes new events
 * since the last sync by tracking the highest ID seen
 */
class FastModeSynchronizer {
    /** @var local_evento_evento_service */
    private $eventoService;

    /** @var EventoApiCache */
    private $cache;

    /** @var EventoLogger */
    private $logger;

    /** @var string Key for storing the high water mark */
    private const HIGH_WATER_MARK_KEY_TEMPLATE = 'fast_mode_high_water_mark_%s';

    /**
     * Constructor
     *
     * @param local_evento_evento_service $eventoService
     * @param EventoApiCache $cache
     * @param EventoLogger $logger
     */
    public function __construct(
        local_evento_evento_service $eventoService,
        EventoApiCache $cache,
        EventoLogger $logger
    ) {
        $this->eventoService = $eventoService;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Get all new events since last synchronization for a specific Veranstalter
     *
     * @param string $veranstalterId The Veranstalter ID to process
     * @param bool $initialize Whether to initialize the high water mark if not present
     * @return array Array of events
     * @throws moodle_exception
     */
    public function getNewEvents(string $veranstalterId, int $categoryId): array {
        // Find the highest Evento ID among existing courses
        $highestMoodleEventoId = $this->getHighestEventoIdFromMoodleCourses($categoryId);
        
        $this->logger->info("Highest Evento ID in Moodle courses: {$highestMoodleEventoId}");
        
        // No need to fetch if we don't have a reference point yet
        if ($highestMoodleEventoId === 0) {
            // Get one batch to analyze
            $events = $this->fetchEventsFromEvento($veranstalterId, 0, 100);
            
            // If no events, we're done
            if (empty($events)) {
                return [];
            }
            
            // Otherwise, these are all new events
            return $this->filterEventsByTimeConstraints($events);
        }
        
        // Fetch events with higher IDs
        $events = $this->fetchEventsFromEvento($veranstalterId, $highestMoodleEventoId - 500, 500);

        $this->logger->info("Evento events with an id higher than {$highestMoodleEventoId} found: " . count($events));
        
        // Filter by time constraints
        return $this->filterEventsByTimeConstraints($events);
    }
    
    /**
     * Get highest Evento ID from existing Moodle courses
     *
     * @param int $categoryId Category ID
     * @return int Highest Evento ID
     */
    private function getHighestEventoIdFromMoodleCourses(int $categoryId): int {
        global $DB;
        
        // Get all courses in this category and subcategories
        $sql = "SELECT c.idnumber 
                FROM {course} c
                JOIN {course_categories} cc ON c.category = cc.id
                WHERE cc.id = :catid OR cc.parent = :parentid
                AND c.idnumber IS NOT NULL AND c.idnumber != ''";
                
        $courses = $DB->get_records_sql($sql, ['catid' => $categoryId, 'parentid' => $categoryId]);
        
        $highestId = 0;
        
        foreach ($courses as $course) {
            // Ensure the idnumber is an Evento ID (could add more validation)
            if (is_numeric($course->idnumber)) {
                $eventoId = (int)$course->idnumber;
                if ($eventoId > $highestId) {
                    $highestId = $eventoId;
                }
            }
        }
        
        return $highestId;
    }
    
    /**
     * Fetch events from Evento API
     *
     * @param string $veranstalterId Veranstalter ID
     * @param int $fromId Starting ID
     * @param int $batchSize Number of events to fetch
     * @return array Array of events
     */
    private function fetchEventsFromEvento(string $veranstalterId, int $fromId, int $batchSize): array {
        try {
            $request = [
                'theEventoAnlassFilter' => [
                    'anlassVeranstalter' => $veranstalterId
                ],
                'theLimitationFilter2' => [
                    'theMaxResultsValue' => $batchSize,
                    'theFromKey' => $fromId,
                    // The to key is needed for the from key to work
                    'theToKey' => 999999
                ]
            ];
            
            $result = $this->eventoService->execute_soap_request(
                'listEventoAnlass',
                $request,
                "Fetching events for Veranstalter {$veranstalterId}, after ID {$fromId}"
            );
            
            if (!property_exists($result, "return")) {
                return [];
            }
            
            $events = $this->eventoService->to_array($result->return);

            $this->logger->info("Evento events found: " . count($events));

            $count = 0;
            foreach ($events as $event) {
                $this->logger->info("{$count} Evento sevent {$event->anlassNummer} mit Start {$event->anlassDatumVon}");
                $count++;
            }

            return $events;
            
            // Filter events with ID > fromId
            // return array_filter($events, function($event) use ($fromId) {
            //     return (int)$event->idAnlass > $fromId;
            // });
        } catch (\Exception $e) {
            $this->logger->error("Error fetching events: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Filter events by time constraints (current and future events)
     *
     * @param array $events Array of events
     * @return array Filtered events
     */
    private function filterEventsByTimeConstraints(array $events): array {
        $now = time();
        $currentYear = (int)date('Y');
        $nextYear = $currentYear + 1;
        
        return array_filter($events, function($event) use ($now, $currentYear, $nextYear) {
            // Skip events without dates
            if (empty($event->anlassDatumVon) || empty($event->anlassDatumBis)) {
                return false;
            }
            
            // Skip events that have already ended
            $endDate = strtotime($event->anlassDatumBis);
            if ($endDate < $now) {
                return false;
            }
            
            // Check if event is in current or next year
            $eventYear = (int)date('Y', strtotime($event->anlassDatumVon));
            return $eventYear === $currentYear || $eventYear === $nextYear;
        });
    }

    /**
     * Initialize high water mark by finding the highest current event ID for a Veranstalter
     *
     * @param string $veranstalterId The Veranstalter ID to initialize
     * @return int The highest event ID found
     * @throws moodle_exception
     */
    public function initializeHighWaterMark(string $veranstalterId): int {
        $this->logger->info("Initializing high water mark for Veranstalter {$veranstalterId}...");

        try {
            // Get a small batch of events sorted by ID descending (if API supports it)
            $request = [
                'theEventoAnlassFilter' => [
                    'anlassVeranstalter' => $veranstalterId
                ],
                'theLimitationFilter2' => [
                    'theMaxResultsValue' => 1,
                    'theSortField' => 'idAnlass' // May not be supported by all APIs
                ]
            ];

            $result = $this->eventoService->execute_soap_request(
                'listEventoAnlass',
                $request,
                "Getting highest ID for Veranstalter {$veranstalterId}"
            );

            $highestId = 0;

            if (property_exists($result, "return")) {
                $events = $this->eventoService->to_array($result->return);

                foreach ($events as $event) {
                    $eventId = (int)$event->idAnlass;
                    if ($eventId > $highestId) {
                        $highestId = $eventId;
                    }
                }
            }

            // If sorting by ID DESC isn't supported, fall back to getting a larger batch
            if ($highestId === 0) {
                $request = [
                    'theEventoAnlassFilter' => [
                        'anlassVeranstalter' => $veranstalterId
                    ],
                    'theLimitationFilter2' => [
                        'theMaxResultsValue' => 500 // Get a reasonably sized batch
                    ]
                ];

                $result = $this->eventoService->execute_soap_request(
                    'listEventoAnlass',
                    $request,
                    "Getting event batch for Veranstalter {$veranstalterId}"
                );

                if (property_exists($result, "return")) {
                    $events = $this->eventoService->to_array($result->return);

                    foreach ($events as $event) {
                        $eventId = (int)$event->idAnlass;
                        if ($eventId > $highestId) {
                            $highestId = $eventId;
                        }
                    }
                }
            }

            if ($highestId === 0) {
                $this->logger->info("No events found for Veranstalter {$veranstalterId}, using 0 as initial high water mark");
                return 0;
            }

            // Store the high water mark
            $this->setHighWaterMark($veranstalterId, $highestId);

            return $highestId;

        } catch (\Exception $e) {
            $this->logger->error(
                "Error initializing high water mark for Veranstalter {$veranstalterId}: " . $e->getMessage()
            );
            // Set a conservative value of 0 on error
            $this->setHighWaterMark($veranstalterId, 0);
            return 0;
        }
    }

    /**
     * Get current high water mark for a specific Veranstalter
     *
     * @param string $veranstalterId The Veranstalter ID
     * @return int|null The current high water mark or null if not set
     */
    public function getHighWaterMark(string $veranstalterId) {
        $key = sprintf(self::HIGH_WATER_MARK_KEY_TEMPLATE, $veranstalterId);
        $value = $this->cache->get($key);
        
        // Log the actual value for debugging
        $this->logger->debug("Retrieved high water mark for {$veranstalterId}: " . var_export($value, true));
        
        // Handle various types of "not set" conditions
        if ($value === false || $value === null || $value === '' || $value === 0) {
            return null;
        }
        
        return (int)$value; // Ensure it's an integer
    }

    /**
     * Set new high water mark for a specific Veranstalter
     *
     * @param string $veranstalterId The Veranstalter ID
     * @param int $value The new high water mark
     */
    private function setHighWaterMark(string $veranstalterId, int $value): void {
        $key = sprintf(self::HIGH_WATER_MARK_KEY_TEMPLATE, $veranstalterId);
        $success = $this->cache->set($key, $value);
        $this->logger->debug("Set high water mark for {$veranstalterId} to {$value}: " . ($success ? 'success' : 'failed'));
        
        // Double-check that it was stored
        $stored = $this->cache->get($key);
        $this->logger->debug("Verification - Retrieved high water mark for {$veranstalterId}: " . var_export($stored, true));
    }

    /**
     * Reset high water mark for a specific Veranstalter (for testing or recovery)
     * 
     * @param string $veranstalterId The Veranstalter ID
     */
    public function resetHighWaterMark(string $veranstalterId): void {
        $key = sprintf(self::HIGH_WATER_MARK_KEY_TEMPLATE, $veranstalterId);
        $this->cache->delete($key);
    }
}
