<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Worker script for parallel event fetching
 *
 * @package    local_eventocoursecreation
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/locklib.php');
require_once(__DIR__.'/../locallib.php');

// Get CLI options
list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'job' => '',
        'worker' => 0,
        'verbose' => false
    ],
    [
        'h' => 'help',
        'j' => 'job',
        'w' => 'worker',
        'v' => 'verbose'
    ]
);

// Display help
if ($options['help'] || empty($options['job'])) {
    $help = "Worker script for parallel event fetching.

Options:
-h, --help       Print this help.
-j, --job        Job ID to process (required).
-w, --worker     Worker ID number (default: 0).
-v, --verbose    Enable verbose output.

Example:
\$ php fetch_worker.php --job=event_fetch_12345 --worker=1 --verbose
";
    echo $help;
    exit(0);
}

// Set up trace and logger
$trace = $options['verbose'] ? new text_progress_trace() : new null_progress_trace();
$logger = new \local_eventocoursecreation\EventoLogger($trace, [
    'worker_id' => (int)$options['worker'],
    'job_id' => $options['job'],
    'pid' => getmypid()
]);

// Register shutdown handler to catch fatal errors
register_shutdown_function(function() use ($logger, $options) {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        $logger->error("Fatal error occurred", [
            'error' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
        
        // Try to update job status
        try {
            $cache = new \local_eventocoursecreation\api\EventoApiCache();
            $factory = new core\lock\lock_factory('paralleleventfetcher');
            $lock = $factory->get_lock("work_status_{$options['job']}", 5, 600);
            
            if ($lock) {
                try {
                    $status = $cache->get("work_status_{$options['job']}");
                    if ($status) {
                        $status['errors']++;
                        if (!isset($status['worker_errors'])) {
                            $status['worker_errors'] = [];
                        }
                        $status['worker_errors'][(int)$options['worker']] = [
                            'error' => $error['message'],
                            'time' => time()
                        ];
                        $cache->set("work_status_{$options['job']}", $status);
                    }
                } finally {
                    $lock->release();
                }
            }
        } catch (Exception $e) {
            // Last resort, can't do much more
            mtrace("Error updating status: " . $e->getMessage());
        }
    }
});

// Initialize dependencies
try {
    $logger->info("Starting worker process");
    
    // Set up signal handling for graceful shutdown if possible
    if (function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function() use ($logger) {
            $logger->info("Received termination signal, shutting down");
            exit(0);
        });
    }
    
    // Create service container with trace for logging
    $container = new \local_eventocoursecreation\ServiceContainer($trace);
    
    // Get the cache service
    $cache = $container->getApiCache();
    
    // Create the parallel fetcher instance
    $fetcher = $container->getParallelEventFetcher();
    
    // Run the worker
    $logger->info("Worker ready, processing job", ['job' => $options['job']]);
    $result = $fetcher->runWorker($options['job'], (int)$options['worker']);
    
    // Report completion
    if ($result) {
        $logger->info("Worker completed successfully");
        exit(0);
    } else {
        $logger->error("Worker failed to complete job");
        exit(1);
    }
    
} catch (Exception $e) {
    $logger->error("Worker failed with exception", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit(1);
}