<?php

namespace local_eventocoursecreation\api;

use DateTime;
use Exception;
use moodle_exception;
use local_eventocoursecreation\util\lock_helper;

/**
 * Parallel Event Fetcher for Evento integration
 * 
 * This class enables parallel fetching of events from the Evento webservice
 * by spawning multiple worker processes that share work through a cache-based
 * queue system. It uses Moodle's locking system to coordinate work.
 * 
 * NOTE: This should only be used in CLI context as it spawns separate processes.
 * 
 * Usage:
 * ```php
 * $fetcher = new ParallelEventFetcher($cache, ['num_threads' => 4]);
 * $events = $fetcher->fetchEventsForMultipleVeranstalter(
 *     ['V001', 'V002'], // Veranstalter IDs
 *     new DateTime('2023-01-01'),
 *     new DateTime('2023-12-31')
 * );
 * ```
 * 
 * @package    local_eventocoursecreation
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ParallelEventFetcher {
    /** @var string Name of the lock factory to use */
    private const LOCK_FACTORY = 'paralleleventfetcher';
    
    /** @var int Timeout for obtaining a lock in seconds */
    private const LOCK_TIMEOUT = 5;
    
    /** @var int Number of worker processes to spawn */
    private $numThreads;
    
    /** @var EventoApiCache */
    private $cache;
    
    /** @var array Configuration */
    private $config;

    /** @var \local_eventocoursecreation\EventoLogger|null */
    private $logger;
    
    /**
     * Constructor
     * 
     * @param EventoApiCache $cache Cache instance
     * @param array $config Configuration options
     * @param \local_eventocoursecreation\EventoLogger|null $logger Optional logger
     */
    public function __construct(
        EventoApiCache $cache, 
        array $config = [],
        \local_eventocoursecreation\EventoLogger $logger = null
    ) {
        $this->cache = $cache;
        $this->logger = $logger;

        // Load from FetcherConfiguration for consistent defaults
        $fetcherConfig = new FetcherConfiguration($config);
        $this->config = $fetcherConfig->getAll();
        
        $this->numThreads = $this->config['num_threads'];
    }
    
    /**
     * Fetch events in parallel for multiple Veranstalter
     * 
     * @param array $veranstalterList List of Veranstalter IDs
     * @param DateTime $fromDate Start date
     * @param DateTime $toDate End date
     * @return array Results indexed by Veranstalter ID
     * @throws moodle_exception If parallel processing is not supported
     */
    public function fetchEventsForMultipleVeranstalter(
        array $veranstalterList,
        DateTime $fromDate,
        DateTime $toDate
    ): array {
        global $CFG;
        
        // Require CLI for this operation
        if (PHP_SAPI !== 'cli') {
            throw new moodle_exception(
                'parallel_requires_cli', 
                'local_eventocoursecreation', 
                '', 
                'Parallel fetching requires CLI context'
            );
        }
        
        if (!$this->hasExecFunction()) {
            throw new moodle_exception(
                'parallel_exec_disabled', 
                'local_eventocoursecreation', 
                '', 
                'Parallel fetching requires the PHP exec function to be enabled'
            );
        }
        
        // Create a unique job ID for this batch
        $jobId = uniqid('event_fetch_', true);
        
        $this->log('info', "Starting parallel fetch job: {$jobId}");
        
        // Create work queue
        $workItems = [];
        foreach ($veranstalterList as $veranstalterId) {
            $workItems[] = [
                'veranstalterId' => $veranstalterId,
                'fromDate' => $fromDate->format('Y-m-d'),
                'toDate' => $toDate->format('Y-m-d')
            ];
        }
        
        // Store work queue in cache
        $this->cache->set("work_queue_{$jobId}", $workItems);
        $this->cache->set("work_results_{$jobId}", []);
        $this->cache->set("work_errors_{$jobId}", []);
        $this->cache->set("work_status_{$jobId}", [
            'total' => count($workItems),
            'completed' => 0,
            'errors' => 0,
            'start_time' => time()
        ]);
        
        // Create worker processes
        $phpBinary = PHP_BINARY;
        $scriptPath = $CFG->dirroot . '/local/eventocoursecreation/cli/fetch_worker.php';
        
        $workers = [];
        for ($i = 0; $i < $this->numThreads; $i++) {
            $command = sprintf(
                '%s %s --job=%s --worker=%d > /dev/null 2>&1 & echo $!',
                $phpBinary,
                $scriptPath,
                escapeshellarg($jobId),
                $i
            );
            
            $pid = exec($command);
            if ($pid) {
                $workers[$i] = [
                    'pid' => (int)$pid,
                    'started' => time(),
                    'heartbeat' => time()
                ];
            }
        }
        
        if (empty($workers)) {
            throw new moodle_exception(
                'parallel_worker_start_failed', 
                'local_eventocoursecreation', 
                '', 
                'Failed to start worker processes'
            );
        }
        
        // Store worker status in cache
        $this->cache->set("worker_status_{$jobId}", $workers);
        
        // Wait for workers to complete or timeout
        $startTime = time();
        $timeout = $this->config['task_timeout'];
        
        $this->log('info', "Started {$this->numThreads} worker processes for job {$jobId}");
        
        while (true) {
            // Check if all work is done
            $status = $this->cache->get("work_status_{$jobId}");
            if ($status['completed'] >= $status['total']) {
                $this->log('info', "All work completed: {$status['completed']}/{$status['total']} items");
                break;
            }
            
            // Check for timeout
            if ((time() - $startTime) > $timeout) {
                $this->log('error', "Operation timed out after {$timeout} seconds");
                // Kill worker processes
                foreach ($workers as $worker) {
                    if (isset($worker['pid'])) {
                        exec("kill -9 {$worker['pid']} 2>/dev/null");
                    }
                }
                break;
            }

            // Check worker health
            $workerStatus = $this->cache->get("worker_status_{$jobId}");
            if ($workerStatus) {
                foreach ($workerStatus as $id => $worker) {
                    if (isset($worker['heartbeat']) && (time() - $worker['heartbeat']) > 30) {
                        $this->log('warning', "Worker {$id} appears to be stuck, attempting restart...");
                        
                        // Try to kill and restart the worker
                        if (isset($worker['pid'])) {
                            exec("kill -9 {$worker['pid']} 2>/dev/null");
                            
                            // Start a new worker
                            $command = sprintf(
                                '%s %s --job=%s --worker=%d > /dev/null 2>&1 & echo $!',
                                $phpBinary,
                                $scriptPath,
                                escapeshellarg($jobId),
                                $id
                            );
                            
                            $pid = exec($command);
                            if ($pid) {
                                $workers[$id] = [
                                    'pid' => (int)$pid,
                                    'started' => time(),
                                    'heartbeat' => time(),
                                    'restarted' => true
                                ];
                                
                                $this->cache->set("worker_status_{$jobId}", $workers);
                                $this->log('info', "Worker {$id} restarted with PID {$pid}");
                            }
                        }
                    }
                }
            }
            
            // Status update
            if ((time() - $startTime) % 10 === 0) {
                $this->log('info', "Progress: {$status['completed']}/{$status['total']} items, {$status['errors']} errors");
            }
            
            // Short sleep to reduce CPU usage
            sleep(1);
        }
        
        // Collect results
        $results = $this->cache->get("work_results_{$jobId}") ?: [];
        
        // Check for any workers that had errors and report
        $status = $this->cache->get("work_status_{$jobId}");
        if ($status['errors'] > 0) {
            $this->log('warning', "Completed with {$status['errors']} errors");
            
            // Get error details
            $errorDetails = $this->cache->get("work_errors_{$jobId}") ?: [];
            foreach ($errorDetails as $veranstalterId => $error) {
                $this->log('error', "Error processing {$veranstalterId}: {$error['message']}");
            }
            
            // Try to recover missing Veranstalter using fallback method
            $missingVeranstalter = [];
            foreach ($veranstalterList as $veranstalterId) {
                if (!isset($results[$veranstalterId]) || isset($results[$veranstalterId]['error'])) {
                    $missingVeranstalter[] = $veranstalterId;
                }
            }
            
            if (!empty($missingVeranstalter) && $this->config['enable_recovery']) {
                $this->log('info', "Attempting to recover " . count($missingVeranstalter) . " failed Veranstalter");
                
                // Create recovery service
                $recoveryService = $this->createRecoveryService();
                
                // Try to fetch each missing one individually
                foreach ($missingVeranstalter as $veranstalterId) {
                    try {
                        $this->log('info', "Recovering {$veranstalterId}...");
                        $events = $recoveryService->fetchAllEvents($veranstalterId, $fromDate, $toDate);
                        $results[$veranstalterId] = $events;
                        $this->log('info', "Recovered {$veranstalterId}: found " . count($events) . " events");
                    } catch (Exception $e) {
                        $this->log('error', "Recovery failed for {$veranstalterId}: " . $e->getMessage());
                    }
                }
            }
        }
        
        // Clean up
        $this->cache->delete("work_queue_{$jobId}");
        $this->cache->delete("work_status_{$jobId}");
        $this->cache->delete("worker_status_{$jobId}");
        
        // Keep results and errors in cache for debugging but with expiry
        $this->cache->set("work_results_{$jobId}", $results, 3600);
        $this->cache->set("work_errors_{$jobId}", $errorDetails ?? [], 3600);
        
        $this->log('info', "Parallel fetching completed for job {$jobId}");
        
        return $results;
    }
    
    /**
     * Check if exec function is available
     * 
     * @return bool Whether exec is available
     */
    private function hasExecFunction(): bool {
        // Check if exec is disabled in php.ini
        $disabledFunctions = explode(',', ini_get('disable_functions'));
        $disabledFunctions = array_map('trim', $disabledFunctions);
        
        return function_exists('exec') && !in_array('exec', $disabledFunctions);
    }
    
    /**
     * Worker process entry point - designed to be called from CLI
     * 
     * @param string $jobId Unique job identifier
     * @param int $workerId Worker number
     * @return bool Success status
     */
    public function runWorker(string $jobId, int $workerId): bool {
        global $CFG;
        require_once($CFG->dirroot . '/local/evento/locallib.php');
        
        $logger = $this->logger ? $this->logger->withContext(['worker_id' => $workerId, 'job_id' => $jobId]) : null;
        
        // Initialize API service
        $eventoService = new \local_evento_evento_service();
        if (!$eventoService->init_call()) {
            $this->log('error', "Worker {$workerId}: Failed to initialize Evento connection", $logger);
            return false;
        }
        
        // Create a lock factory
        $lockFactory = lock_helper::get_lock_factory(self::LOCK_FACTORY);
        
        // Set initial heartbeat
        $this->updateWorkerHeartbeat($jobId, $workerId);
        
        $retryCount = 0;
        $maxRetries = 3;
        
        while (true) {
            // Update heartbeat regularly
            $this->updateWorkerHeartbeat($jobId, $workerId);
            
            // Try to get a lock on the work queue
            $lock = $lockFactory->get_lock("work_queue_{$jobId}", self::LOCK_TIMEOUT);
            if (!$lock) {
                $retryCount++;
                if ($retryCount > $maxRetries) {
                    $this->log('error', "Worker {$workerId}: Failed to obtain lock after {$maxRetries} retries, exiting", $logger);
                    return false;
                }
                
                $this->log('warning', "Worker {$workerId}: Failed to obtain lock, retrying ({$retryCount}/{$maxRetries})...", $logger);
                sleep(1);
                continue;
            }
            
            // Reset retry counter on success
            $retryCount = 0;
            
            try {
                // Get work queue
                $workQueue = $this->cache->get("work_queue_{$jobId}");
                if (empty($workQueue)) {
                    // No more work
                    $lock->release();
                    $this->log('info', "Worker {$workerId}: No more work items, exiting", $logger);
                    return true;
                }
                
                // Get an item to process
                $workItem = array_shift($workQueue);
                
                // Update work queue
                $this->cache->set("work_queue_{$jobId}", $workQueue);
                
                // Release lock so other workers can get work
                $lock->release();
                
                // Process the work item
                if ($workItem) {
                    $this->processWorkItem($jobId, $workerId, $workItem, $eventoService, $logger);
                }
                
            } catch (Exception $e) {
                // Release lock in case of error
                $lock->release();
                
                $this->log('error', "Worker {$workerId}: Error - " . $e->getMessage(), $logger);
                
                // Update status
                $statusLock = $lockFactory->get_lock("work_status_{$jobId}", self::LOCK_TIMEOUT);
                if ($statusLock) {
                    try {
                        $status = $this->cache->get("work_status_{$jobId}");
                        $status['errors']++;
                        $status['completed']++;
                        $this->cache->set("work_status_{$jobId}", $status);
                    } finally {
                        $statusLock->release();
                    }
                }
                
                // Continue to next work item
                continue;
            }
            
            // Short delay to prevent overloading the API
            usleep(500000); // 0.5 seconds
        }
        
        return true;
    }
    
    /**
     * Process a single work item
     * 
     * @param string $jobId Job ID
     * @param int $workerId Worker ID
     * @param array $workItem Work item to process
     * @param \local_evento_evento_service $eventoService Evento service
     * @param \local_eventocoursecreation\EventoLogger|null $logger Logger instance
     */
    private function processWorkItem(
        string $jobId, 
        int $workerId, 
        array $workItem, 
        \local_evento_evento_service $eventoService,
        \local_eventocoursecreation\EventoLogger $logger = null
    ): void {
        $veranstalterId = $workItem['veranstalterId'];
        $fromDate = new DateTime($workItem['fromDate']);
        $toDate = new DateTime($workItem['toDate']);
        
        $this->log('info', "Processing Veranstalter {$veranstalterId}", $logger);
        
        try {
            $trace = new \text_progress_trace();
            // Create SmartEventFetcher for this task
            $eventFetcher = new SmartEventFetcher(
                $eventoService,
                $this->cache,
                $logger ?? new \local_eventocoursecreation\EventoLogger($trace),
                [
                    'batch_size' => $this->config['chunk_size'],
                    'adaptive_batch_sizing' => true,
                    'enable_incremental' => true
                ]
            );
            
            // Fetch events
            $events = $eventFetcher->fetchAllEvents($veranstalterId, $fromDate, $toDate);
            
            // Lock for updating results
            $lockFactory = lock_helper::get_lock_factory(self::LOCK_FACTORY);
            $lock = $lockFactory->get_lock("work_results_{$jobId}", self::LOCK_TIMEOUT);
            
            if ($lock) {
                try {
                    // Update results with detailed metadata
                    $results = $this->cache->get("work_results_{$jobId}") ?: [];
                    $results[$veranstalterId] = [
                        'events' => $events,
                        'metadata' => [
                            'worker_id' => $workerId,
                            'processed_at' => time(),
                            'event_count' => count($events),
                            'performance' => $eventFetcher->getStats()
                        ]
                    ];
                    $this->cache->set("work_results_{$jobId}", $results);
                } finally {
                    $lock->release();
                }
            }
            
            // Update status
            $statusLock = $lockFactory->get_lock("work_status_{$jobId}", self::LOCK_TIMEOUT, 600);
            if ($statusLock) {
                try {
                    $status = $this->cache->get("work_status_{$jobId}");
                    $status['completed']++;
                    $this->cache->set("work_status_{$jobId}", $status);
                } finally {
                    $statusLock->release();
                }
            }
            
            $this->log('info', "Completed Veranstalter {$veranstalterId}, found " . count($events) . " events", $logger);
            
            // Memory management
            $events = null;
            gc_collect_cycles();
            
        } catch (Exception $e) {
            $this->log('error', "Error processing Veranstalter {$veranstalterId} - " . $e->getMessage(), $logger);
            
            // Detailed error data
            $errorData = [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'worker_id' => $workerId,
                'veranstalter_id' => $veranstalterId,
                'timestamp' => time()
            ];
            
            // Store error details
            $this->storeErrorData($jobId, $veranstalterId, $errorData);
            
            // Update status
            $statusLock = lock_helper::get_lock(self::LOCK_FACTORY, "work_status_{$jobId}", self::LOCK_TIMEOUT, 600);
            if ($statusLock) {
                try {
                    $status = $this->cache->get("work_status_{$jobId}");
                    $status['errors']++;
                    $status['completed']++;
                    $this->cache->set("work_status_{$jobId}", $status);
                } finally {
                    $statusLock->release();
                }
            }
            
            // Add to results but with error
            $lock = lock_helper::get_lock(self::LOCK_FACTORY, "work_results_{$jobId}", self::LOCK_TIMEOUT, 600);
            if ($lock) {
                try {
                    $results = $this->cache->get("work_results_{$jobId}") ?: [];
                    $results[$veranstalterId] = [
                        'error' => $e->getMessage(),
                        'metadata' => [
                            'worker_id' => $workerId,
                            'processed_at' => time(),
                            'error_trace' => $errorData
                        ]
                    ];
                    $this->cache->set("work_results_{$jobId}", $results);
                } finally {
                    $lock->release();
                }
            }
        }
    }
    
    /**
     * Store error data in the cache
     *
     * @param string $jobId Job ID
     * @param string $veranstalterId Veranstalter ID
     * @param array $errorData Error details
     */
    private function storeErrorData(string $jobId, string $veranstalterId, array $errorData): void {
        $lock = lock_helper::get_lock(self::LOCK_FACTORY, "work_errors_{$jobId}", self::LOCK_TIMEOUT, 600);
        
        if ($lock) {
            try {
                $errors = $this->cache->get("work_errors_{$jobId}") ?: [];
                $errors[$veranstalterId] = $errorData;
                $this->cache->set("work_errors_{$jobId}", $errors);
            } finally {
                $lock->release();
            }
        }
    }
    
    /**
     * Update worker heartbeat timestamp
     *
     * @param string $jobId Job ID
     * @param int $workerId Worker ID
     */
    private function updateWorkerHeartbeat(string $jobId, int $workerId): void {
        $lock = lock_helper::get_lock(self::LOCK_FACTORY, "worker_status_{$jobId}", self::LOCK_TIMEOUT, 600);
        
        if ($lock) {
            try {
                $workers = $this->cache->get("worker_status_{$jobId}") ?: [];
                if (isset($workers[$workerId])) {
                    $workers[$workerId]['heartbeat'] = time();
                    $this->cache->set("worker_status_{$jobId}", $workers);
                }
            } finally {
                $lock->release();
            }
        }
    }
    
    /**
     * Create a recovery service for fallback fetching
     *
     * @return SmartEventFetcher Recovery fetcher with conservative settings
     */
    protected function createRecoveryService(): SmartEventFetcher {
        global $CFG;
        require_once($CFG->dirroot . '/local/evento/locallib.php');
        
        $eventoService = new \local_evento_evento_service();
        if (!$eventoService->init_call()) {
            throw new moodle_exception('Failed to initialize Evento connection for recovery');
        }
        
        $trace = new \text_progress_trace();
        $logger = $this->logger ?? new \local_eventocoursecreation\EventoLogger($trace);
        
        // Create with conservative settings
        return new SmartEventFetcher(
            $eventoService,
            $this->cache,
            $logger->withContext(['context' => 'recovery']),
            [
                'batch_size' => 50,               // Smaller batch size
                'min_batch_size' => 10,           
                'max_batch_size' => 100,          // More conservative max
                'adaptive_batch_sizing' => true,
                'date_chunk_fallback' => true,    // Enable date chunking fallback
                'date_chunk_days' => 30,          // Smaller chunk size
                'max_api_retries' => 5,           // More retries
                'retry_delay_base' => 2,          // Longer delays
                'cache_ttl' => 3600,
                'enable_incremental' => false     // Disable incremental for recovery
            ]
        );
    }
    
    /**
     * Log a message if logger is available
     *
     * @param string $level Log level
     * @param string $message Message to log
     * @param \local_eventocoursecreation\EventoLogger|null $logger Logger to use (or default)
     */
    private function log(
        string $level, 
        string $message, 
        \local_eventocoursecreation\EventoLogger $logger = null
    ): void {
        $logger = $logger ?? $this->logger;
        
        if ($logger) {
            switch ($level) {
                case 'error':
                    $logger->error($message);
                    break;
                case 'warning':
                    $logger->warning($message);
                    break;
                case 'info':
                    $logger->info($message);
                    break;
                case 'debug':
                    $logger->debug($message);
                    break;
            }
        } else {
            // Basic fallback to mtrace
            mtrace("[{$level}] {$message}");
        }
    }
}