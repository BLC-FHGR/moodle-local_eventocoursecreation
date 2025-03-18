<?php

namespace local_eventocoursecreation\api;

use DateTime;
use Exception;
use local_evento_evento_service;
use local_eventocoursecreation\EventoLogger;

/**
 * Smart Evento Event Fetcher with adaptive strategies
 *
 * Optimizes event fetching from Evento based on runtime conditions
 */
class SmartEventFetcher {
    /** @var local_evento_evento_service */
    private $eventoService;

    /** @var EventoApiCache */
    private $cache;

    /** @var EventoLogger */
    private $logger;

    /** @var array */
    private $config;

    /** @var array Statistics for performance monitoring */
    private $stats = [
        'api_calls' => 0,
        'total_events' => 0,
        'errors' => 0,
        'cache_hits' => 0,
        'execution_time' => 0
    ];

    /**
     * Constructor
     *
     * @param local_evento_evento_service $eventoService
     * @param EventoApiCache $cache
     * @param EventoLogger $logger
     * @param array $config Optional configuration
     */
    public function __construct(
        local_evento_evento_service $eventoService,
        EventoApiCache $cache,
        EventoLogger $logger,
        array $config = []
    ) {
        $this->eventoService = $eventoService;
        $this->cache = $cache;
        $this->logger = $logger;

        // Set default configuration
        $this->config = array_merge([
            'batch_size' => 200,                // Default batch size
            'min_batch_size' => 10,             // Minimum batch size in case of errors
            'max_batch_size' => 1000,           // Maximum batch size to try
            'adaptive_batch_sizing' => true,    // Dynamically adjust batch size
            'date_chunk_fallback' => true,      // Fall back to date chunking
            'date_chunk_days' => 90,            // Size of date chunks in days
            'max_api_retries' => 3,             // Max retries for API calls
            'retry_delay_base' => 1,            // Base seconds for retry delay (exponential backoff)
            'cache_ttl' => 3600,                // Cache TTL in seconds
            'enable_incremental' => true,       // Enable incremental fetching
            'parallel_requests' => false,       // Enable parallel requests (experimental)
            'max_parallel_threads' => 2,        // Maximum parallel requests if enabled
            'error_threshold' => 5,             // Threshold for switching strategies
        ], $config);
    }

    /**
     * Fetch all events for a Veranstalter using optimal strategy
     *
     * @param string $veranstalterId
     * @param DateTime|null $fromDate
     * @param DateTime|null $toDate
     * @param bool $forceRefresh Force refresh ignoring cache
     * @return array
     */
    public function fetchAllEvents(
        string $veranstalterId,
        ?DateTime $fromDate = null,
        ?DateTime $toDate = null,
        bool $forceRefresh = false
    ): array {
        $startTime = microtime(true);

        $this->logger->info("Starting event fetch for Veranstalter: {$veranstalterId}");

        // Default dates if not provided
        $fromDate = $fromDate ?? new DateTime('-1 year');
        $toDate = $toDate ?? new DateTime('+2 years');

        // Try to get from cache first if not forcing refresh
        if (!$forceRefresh) {
            $cacheKey = $this->buildCacheKey($veranstalterId, $fromDate, $toDate);
            $cachedEvents = $this->cache->get($cacheKey);

            if ($cachedEvents !== false) {
                $this->stats['cache_hits']++;
                $this->logger->info("Using cached events: found " . count($cachedEvents) . " events");
                return $cachedEvents;
            }
        }

        // Find the best strategy based on prior performance and availability
        if ($this->config['enable_incremental'] && $this->canUseIncrementalStrategy($veranstalterId)) {
            $events = $this->fetchUsingIncrementalStrategy($veranstalterId, $fromDate, $toDate);
        } else {
            // Start with pagination strategy
            try {
                $events = $this->fetchUsingAdaptivePagination($veranstalterId, $fromDate, $toDate);
            } catch (Exception $e) {
                $this->logger->error("Pagination strategy failed, falling back to date chunking: " . $e->getMessage());
                $this->stats['errors']++;

                // Fallback to date chunking
                if ($this->config['date_chunk_fallback']) {
                    $events = $this->fetchUsingDateChunking($veranstalterId, $fromDate, $toDate);
                } else {
                    throw $e; // Re-throw if fallback is disabled
                }
            }
        }

        // Cache the results
        $cacheKey = $this->buildCacheKey($veranstalterId, $fromDate, $toDate);
        $this->cache->set($cacheKey, $events, $this->config['cache_ttl']);

        // Update stats
        $this->stats['total_events'] = count($events);
        $this->stats['execution_time'] = microtime(true) - $startTime;

        $this->logger->info(sprintf(
            "Fetch complete. Retrieved %d events in %.2f seconds (%d API calls, %d errors)",
            $this->stats['total_events'],
            $this->stats['execution_time'],
            $this->stats['api_calls'],
            $this->stats['errors']
        ));

        return $events;
    }

    /**
     * Build cache key for event results
     */
    private function buildCacheKey(string $veranstalterId, DateTime $fromDate, DateTime $toDate): string {
        return "evento_events_{$veranstalterId}_{$fromDate->format('Ymd')}_{$toDate->format('Ymd')}";
    }

    /**
     * Check if incremental strategy can be used based on previous data
     */
    private function canUseIncrementalStrategy(string $veranstalterId): bool {
        // Get last known event id for this Veranstalter
        $lastEventId = $this->cache->get("evento_last_id_{$veranstalterId}");

        // Only use if we have a previous high watermark
        return $lastEventId !== false && $lastEventId > 0;
    }

    /**
     * Fetch using incremental strategy - only get new events since last fetch
     */
    private function fetchUsingIncrementalStrategy(
        string $veranstalterId,
        DateTime $fromDate,
        DateTime $toDate
    ): array {
        $lastEventId = $this->cache->get("evento_last_id_{$veranstalterId}");

        $this->logger->info("Using incremental strategy, fetching events after ID: {$lastEventId}");

        try {
            // Basic request with theFromKey to start after the last known ID
            $request = [
                'theEventoAnlassFilter' => [
                    'anlassVeranstalter' => $veranstalterId
                ],
                'theLimitationFilter2' => [
                    'theFromDate' => $fromDate->format(LOCAL_EVENTO_DATETIME_FORMAT),
                    'theToDate' => $toDate->format(LOCAL_EVENTO_DATETIME_FORMAT),
                    'theFromKey' => $lastEventId, // Start after the last known ID
                    'theMaxResultsValue' => $this->config['batch_size']
                ]
            ];

            $startTime = microtime(true);
            $this->stats['api_calls']++;

            $result = $this->eventoService->execute_soap_request(
                'listEventoAnlass',
                $request,
                "Incremental fetch for {$veranstalterId}, starting from ID {$lastEventId}"
            );

            // Process results
            if (!property_exists($result, "return")) {
                $this->logger->info("No new events found");

                // Load previous events from cache
                $previousEvents = $this->cache->get("evento_all_events_{$veranstalterId}") ?: [];
                return $previousEvents;
            }

            // Convert to array
            $newEvents = $this->eventoService->to_array($result->return);

            // Update highest event ID
            $highestId = $this->findHighestEventId($newEvents);
            if ($highestId > $lastEventId) {
                $this->cache->set("evento_last_id_{$veranstalterId}", $highestId);
            }

            // Get previous events from cache
            $previousEvents = $this->cache->get("evento_all_events_{$veranstalterId}") ?: [];

            // Merge with de-duplication
            $allEvents = $this->mergeEvents($previousEvents, $newEvents);

            // Store the complete list back in cache
            $this->cache->set("evento_all_events_{$veranstalterId}", $allEvents);

            $this->logger->info(sprintf(
                "Incremental fetch successful: %d new events, %d total events",
                count($newEvents),
                count($allEvents)
            ));

            return $allEvents;

        } catch (Exception $e) {
            $this->logger->error("Incremental strategy failed: " . $e->getMessage());
            $this->stats['errors']++;

            // Fall back to full pagination
            return $this->fetchUsingAdaptivePagination($veranstalterId, $fromDate, $toDate);
        }
    }

    /**
     * Find highest event ID in a set of events
     */
    private function findHighestEventId(array $events): int {
        $highest = 0;
        foreach ($events as $event) {
            $eventId = (int)($event->idAnlass ?? 0);
            if ($eventId > $highest) {
                $highest = $eventId;
            }
        }
        return $highest;
    }

    /**
     * Merge events with de-duplication by ID
     */
    private function mergeEvents(array $existingEvents, array $newEvents): array {
        $merged = [];
        $seenIds = [];

        // Add existing events to the result and track IDs
        foreach ($existingEvents as $event) {
            $eventId = $event->idAnlass ?? null;
            if ($eventId) {
                $merged[] = $event;
                $seenIds[$eventId] = true;
            }
        }

        // Add new events if not already present
        foreach ($newEvents as $event) {
            $eventId = $event->idAnlass ?? null;
            if ($eventId && !isset($seenIds[$eventId])) {
                $merged[] = $event;
                $seenIds[$eventId] = true;
            }
        }

        return $merged;
    }

    /**
     * Fetch events using adaptive pagination to handle API limitations
     */
    private function fetchUsingAdaptivePagination(
        string $veranstalterId,
        DateTime $fromDate,
        DateTime $toDate
    ): array {
        $this->logger->info("Using adaptive pagination strategy");

        $allEvents = [];
        $seenIds = [];
        $fromKey = 0;
        $batchSize = $this->config['batch_size'];
        $consecutiveErrors = 0;

        while (true) {
            try {
                $this->logger->info("Fetching batch with fromKey={$fromKey}, batchSize={$batchSize}");

                $request = [
                    'theEventoAnlassFilter' => [
                        'anlassVeranstalter' => $veranstalterId
                    ],
                    'theLimitationFilter2' => [
                        'theFromDate' => $fromDate->format(LOCAL_EVENTO_DATETIME_FORMAT),
                        'theToDate' => $toDate->format(LOCAL_EVENTO_DATETIME_FORMAT),
                        'theMaxResultsValue' => $batchSize,
                        'theFromKey' => $fromKey
                    ]
                ];

                $this->stats['api_calls']++;

                $response = $this->eventoService->execute_soap_request(
                    'listEventoAnlass',
                    $request,
                    "Pagination fetch for {$veranstalterId}, fromKey={$fromKey}, batchSize={$batchSize}"
                );

                if (!property_exists($response, "return")) {
                    // No more data
                    break;
                }

                $batch = $this->eventoService->to_array($response->return);
                if (empty($batch)) {
                    // Done, no more results
                    break;
                }

                // Reset error counter on success
                $consecutiveErrors = 0;

                // If this batch was successful and we're using adaptive sizing, try increasing
                if ($this->config['adaptive_batch_sizing'] && $batchSize < $this->config['max_batch_size']) {
                    $nextBatchSize = min($batchSize * 2, $this->config['max_batch_size']);
                    $this->logger->info("Adapting batch size from {$batchSize} to {$nextBatchSize}");
                    $batchSize = $nextBatchSize;
                }

                // De-duplicate and add to results
                foreach ($batch as $event) {
                    $uniqueId = $event->idAnlass ?? null;
                    if (!$uniqueId || isset($seenIds[$uniqueId])) {
                        continue;
                    }
                    $allEvents[] = $event;
                    $seenIds[$uniqueId] = true;
                }

                // Find highest ID for next batch
                $highestId = 0;
                foreach ($batch as $event) {
                    $eventId = (int)($event->idAnlass ?? 0);
                    if ($eventId > $highestId) {
                        $highestId = $eventId;
                    }
                }

                if ($highestId <= $fromKey) {
                    // No progress being made, break to avoid infinite loop
                    $this->logger->info("No progress made in pagination, ending fetch");
                    break;
                }

                // Set fromKey to the highest ID + 1 for next batch
                $fromKey = $highestId + 1;

                // If we got fewer than batchSize, that might be the last page
                if (count($batch) < $batchSize) {
                    // Do one more fetch to make sure
                    $this->logger->info("Received fewer than batch size, checking for more data");
                    continue;
                }

            } catch (Exception $e) {
                $this->stats['errors']++;
                $consecutiveErrors++;

                $this->logger->error("Error in pagination batch: " . $e->getMessage());

                // Switch strategies if we hit the error threshold
                if ($consecutiveErrors >= $this->config['error_threshold']) {
                    $this->logger->error("Error threshold reached, switching to date chunking");

                    // Save what we've already collected
                    $remainingEvents = $this->fetchUsingDateChunking($veranstalterId, $fromDate, $toDate);
                    $allEvents = $this->mergeEvents($allEvents, $remainingEvents);
                    break;
                }

                // Adapt batch size downward on error if using adaptive sizing
                if ($this->config['adaptive_batch_sizing'] && $batchSize > $this->config['min_batch_size']) {
                    $nextBatchSize = max($batchSize / 2, $this->config['min_batch_size']);
                    $this->logger->info("Reducing batch size from {$batchSize} to {$nextBatchSize} after error");
                    $batchSize = (int)$nextBatchSize;
                }

                // Sleep with exponential backoff
                $delay = $this->config['retry_delay_base'] * pow(2, $consecutiveErrors - 1);
                $this->logger->info("Retrying after {$delay}s delay");
                sleep((int)$delay);
            }
        }

        // Update highest ID in cache for future incremental fetches
        if (!empty($allEvents)) {
            $highestId = $this->findHighestEventId($allEvents);
            $this->cache->set("evento_last_id_{$veranstalterId}", $highestId);
            // Store all events
            $this->cache->set("evento_all_events_{$veranstalterId}", $allEvents);
        }

        $this->logger->info("Pagination complete, retrieved " . count($allEvents) . " events");

        return $allEvents;
    }

    /**
     * Fetch events by breaking date range into smaller chunks
     */
    private function fetchUsingDateChunking(
        string $veranstalterId,
        DateTime $fromDate,
        DateTime $toDate
    ): array {
        $this->logger->info("Using date chunking strategy");

        $allEvents = [];
        $seenIds = [];

        // Clone to avoid modifying originals
        $current = clone $fromDate;
        $endDate = clone $toDate;

        // Calculate chunk size in days
        $chunkDays = $this->config['date_chunk_days'];

        while ($current < $endDate) {
            // Create the next chunk end
            $chunkEnd = clone $current;
            $chunkEnd->modify("+{$chunkDays} days");

            if ($chunkEnd > $endDate) {
                $chunkEnd = $endDate;
            }

            $this->logger->info("Fetching chunk from {$current->format('Y-m-d')} to {$chunkEnd->format('Y-m-d')}");

            try {
                $request = [
                    'theEventoAnlassFilter' => [
                        'anlassVeranstalter' => $veranstalterId
                    ],
                    'theLimitationFilter2' => [
                        'theFromDate' => $current->format(LOCAL_EVENTO_DATETIME_FORMAT),
                        'theToDate' => $chunkEnd->format(LOCAL_EVENTO_DATETIME_FORMAT),
                        'theMaxResultsValue' => $this->config['min_batch_size'] // Use smaller batch size for reliability
                    ]
                ];

                $this->stats['api_calls']++;

                $response = $this->eventoService->execute_soap_request(
                    'listEventoAnlass',
                    $request,
                    "Date chunk fetch for {$veranstalterId}, {$current->format('Y-m-d')} to {$chunkEnd->format('Y-m-d')}"
                );

                if (property_exists($response, "return")) {
                    $batch = $this->eventoService->to_array($response->return);

                    // De-duplicate and add to results
                    foreach ($batch as $event) {
                        $uniqueId = $event->idAnlass ?? null;
                        if ($uniqueId && !isset($seenIds[$uniqueId])) {
                            $allEvents[] = $event;
                            $seenIds[$uniqueId] = true;
                        }
                    }
                }

            } catch (Exception $e) {
                $this->stats['errors']++;
                $this->logger->error("Error in date chunk: " . $e->getMessage());

                // If we hit an error, reduce chunk size for next iteration
                $chunkDays = max(7, (int)($chunkDays / 2));
                $this->logger->info("Reducing chunk size to {$chunkDays} days");

                // Skip ahead a bit to avoid the problematic date range
                $current->modify("+1 day");
                continue;
            }

            // Move current pointer to the next chunk's start
            $current = clone $chunkEnd;
            $current->modify('+1 day');
        }

        $this->logger->info("Date chunking complete, retrieved " . count($allEvents) . " events");

        return $allEvents;
    }

    /**
     * Get performance statistics from the fetcher
     *
     * @return array
     */
    public function getStats(): array {
        return $this->stats;
    }
}
