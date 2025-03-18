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
 * CLI script to run Evento course creation with parallel mode
 *
 * @package    local_eventocoursecreation
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/eventocoursecreation/classes/util/lock_helper.php');
require_once(__DIR__.'/../locallib.php');

// Get CLI options.
list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'threads' => 2,
        'verbose' => false,
        'categories' => '',
        'timeout' => 1800,  // 30 minutes default
        'preview' => false,
    ],
    [
        'h' => 'help',
        't' => 'threads',
        'v' => 'verbose',
        'c' => 'categories',
        'p' => 'preview',
    ]
);

// Display help.
if ($options['help']) {
    $help = "Run Evento course creation with parallel mode enabled.

Options:
-h, --help              Print this help.
-t, --threads           Number of parallel threads (default: 2).
-v, --verbose           Enable verbose output.
-c, --categories        Comma-separated list of category IDs to process (optional).
-p, --preview           Preview mode - don't actually create courses.
    --timeout           Maximum execution time in seconds (default: 1800).

Example:
\$ php sync_parallel.php --threads=4 --verbose --categories=2,5,8
";
    echo $help;
    exit(0);
}

// Handle signals for clean shutdown if pcntl extension is available
$originalMode = get_config('local_eventocoursecreation', 'fetching_mode');
if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function() use (&$originalMode) {
        cli_writeln("\nInterrupted! Cleaning up...");
        // Restore original fetching mode
        set_config('fetching_mode', $originalMode, 'local_eventocoursecreation');
        cli_writeln("Restored fetching mode to: {$originalMode}");
        exit(1);
    });
}

// Set up trace
$trace = $options['verbose'] ? new text_progress_trace() : new null_progress_trace();

// Start time tracking
$startTime = time();
$timeoutTime = $startTime + (int)$options['timeout'];

// Create the service container
$container = new \local_eventocoursecreation\ServiceContainer($trace);

// Configure parallel threads in plugin settings
if ($options['threads']) {
    $threads = max(1, min(8, (int)$options['threads'])); // Limit between 1-8 threads
    set_config('max_parallel_threads', $threads, 'local_eventocoursecreation');
    cli_writeln("Set parallel threads to: {$threads}");
}

// Parse category IDs if provided
// Parse Veranstalter IDs if provided
$veranstalterIds = [];
$categoryIds = [];
if (!empty($options['categories'])) {
    global $DB;
    $veranstalterIds = array_map('trim', explode(',', $options['categories']));
    cli_writeln("Looking for categories matching Veranstalter IDs: " . implode(', ', $veranstalterIds));
    
    // Find categories that match these Veranstalter IDs (looking in idnumber field)
    foreach ($veranstalterIds as $veranstalterId) {
        // Try exact match on idnumber first
        $sql = "SELECT cc.id, cc.name, cc.idnumber 
                FROM {course_categories} cc 
                WHERE cc.idnumber = :idnumber";
        $category = $DB->get_record_sql($sql, ['idnumber' => $veranstalterId]);
        
        if ($category) {
            $categoryIds[] = (int)$category->id;
            cli_writeln("Found category '{$category->name}' (ID: {$category->id}) with exact idnumber match: {$veranstalterId}");
            continue;
        }
        
        // Try to find categories with idnumber containing the Veranstalter ID
        $sql = "SELECT cc.id, cc.name, cc.idnumber 
                FROM {course_categories} cc 
                WHERE cc.idnumber LIKE :idnumber";
        $categories = $DB->get_records_sql($sql, ['idnumber' => "%{$veranstalterId}%"]);
        
        if (!empty($categories)) {
            foreach ($categories as $category) {
                $categoryIds[] = (int)$category->id;
                cli_writeln("Found category '{$category->name}' (ID: {$category->id}) containing Veranstalter ID in idnumber: {$veranstalterId}");
            }
        } else {
            // Try to find categories with name containing the Veranstalter ID as a last resort
            $sql = "SELECT cc.id, cc.name, cc.idnumber 
                    FROM {course_categories} cc 
                    WHERE cc.name LIKE :name";
            $categories = $DB->get_records_sql($sql, ['name' => "%{$veranstalterId}%"]);
            
            if (!empty($categories)) {
                foreach ($categories as $category) {
                    $categoryIds[] = (int)$category->id;
                    cli_writeln("Found category '{$category->name}' (ID: {$category->id}) with name matching Veranstalter ID: {$veranstalterId}");
                }
            } else {
                cli_writeln("Warning: Could not find any categories for Veranstalter ID: {$veranstalterId}");
            }
        }
    }
    
    // Remove duplicates
    $categoryIds = array_unique($categoryIds);
    
    if (empty($categoryIds)) {
        cli_error("No valid categories found matching Veranstalter IDs: " . implode(', ', $veranstalterIds));
    }
    
    cli_writeln("Processing categories with IDs: " . implode(', ', $categoryIds));
}

// Set preview mode if requested
if ($options['preview']) {
    set_config('preview_mode', 1, 'local_eventocoursecreation');
    cli_writeln("Running in preview mode (no courses will be created)");
}

// Temporarily override fetching mode to use parallel mode
set_config('fetching_mode', 'parallel', 'local_eventocoursecreation');
cli_writeln("Temporarily set fetching mode to: parallel");

try {
    // Show initial progress message
    cli_writeln("Starting parallel synchronization...");
    
    // Get the service and initialize Evento connection
    $courseCreationService = $container->getCourseCreationService();
    
    // Run the synchronization
    cli_writeln("Starting synchronization with parallel mode...");
    
    $status = 0;
    
    if (!empty($categoryIds)) {
        // Process specific categories
        cli_writeln("Processing " . count($categoryIds) . " specific categories");
        $status = processSpecificCategories($courseCreationService, $categoryIds, $container);
    } else {
        // Process all categories
        cli_writeln("Processing all Evento-enabled categories");
        $status = $courseCreationService->synchronizeAll();
    }
    
    // Check if we need to exit due to timeout
    if (time() > $timeoutTime) {
        cli_writeln("Execution time limit reached. Some operations may not have completed.");
    }
    
    // Report status
    if ($status === 0) {
        cli_writeln("Synchronization completed successfully");
    } else if ($status === 1) {
        cli_writeln("Synchronization completed with errors");
    } else if ($status === 2) {
        cli_writeln("Synchronization skipped (plugin disabled or no data)");
    }
    
    // Display execution time
    $executionTime = time() - $startTime;
    $hours = floor($executionTime / 3600);
    $minutes = floor(($executionTime % 3600) / 60);
    $seconds = $executionTime % 60;
    cli_writeln(sprintf(
        "Total execution time: %02d:%02d:%02d",
        $hours,
        $minutes,
        $seconds
    ));
    
} catch (Exception $e) {
    cli_error("Error: " . $e->getMessage());
} finally {
    // Restore original fetching mode
    set_config('fetching_mode', $originalMode, 'local_eventocoursecreation');
    cli_writeln("Restored fetching mode to: {$originalMode}");
    
    // Restore preview mode
    if ($options['preview']) {
        set_config('preview_mode', 0, 'local_eventocoursecreation');
    }
}

exit(0);

/**
 * Process specific categories without relying on a dedicated method in CourseCreationService
 * 
 * @param \local_eventocoursecreation\CourseCreationService $service The course creation service
 * @param array $categoryIds The category IDs to process
 * @param \local_eventocoursecreation\ServiceContainer $container Service container
 * @return int Status code
 */
function processSpecificCategories(
    \local_eventocoursecreation\CourseCreationService $service,
    array $categoryIds,
    \local_eventocoursecreation\ServiceContainer $container
): int {
    global $DB;
    
    $logger = $container->getLogger();
    $eventoService = $container->getEventoService();
    
    if (!$eventoService->init_call()) {
        $logger->error("Failed to initialize Evento connection");
        return 2;
    }
    
    // Get active Veranstalter list
    $veranstalterList = $eventoService->get_active_veranstalter();
    if (empty($veranstalterList)) {
        $logger->info("No active Veranstalter found");
        return 2;
    }
    
    // Create a map of category ID to Veranstalter ID using database queries
    $categoryToVeranstalterMap = [];
    
    // Get all Evento category settings
    list($sqlIn, $params) = $DB->get_in_or_equal($categoryIds, SQL_PARAMS_NAMED);
    $sql = "SELECT ec.*, cc.idnumber, cc.name 
            FROM {eventocoursecreation} ec
            JOIN {course_categories} cc ON cc.id = ec.category
            WHERE ec.category $sqlIn";

    try {
        $settings = $DB->get_records_sql($sql, $params);
        $logger->info("Found " . count($settings) . " Evento category settings");
        
        if (empty($settings)) {
            $logger->warning("No Evento category settings found for the specified categories");
        }
    } catch (\Exception $e) {
        $logger->error("Database error when fetching category settings: " . $e->getMessage());
        return 1;
    }
    
    foreach ($settings as $setting) {
        // Parse module numbers from idnumber to find Veranstalter
        $idnumber = $setting->idnumber;
        $moduleNumbers = [];
        
        // Extract module numbers if using standard format
        if (strpos($idnumber, EVENTOCOURSECREATION_IDNUMBER_PREFIX) === 0) {
            $parts = explode(EVENTOCOURSECREATION_IDNUMBER_DELIMITER, $idnumber);
            array_shift($parts); // Remove prefix
            $moduleNumbers = array_filter($parts, 'trim');
        }
        
        if (!empty($moduleNumbers)) {
            // Try to find matching Veranstalter
            foreach ($veranstalterList as $veranstalter) {
                foreach ($moduleNumbers as $moduleNumber) {
                    // Match Veranstalter based on module number
                    // Adjust this logic based on your specific matching rules
                    if (strpos($veranstalter->benutzerName, $moduleNumber) !== false ||
                        (property_exists($veranstalter, 'IDBenutzer') && $veranstalter->IDBenutzer == $moduleNumber)) {
                        $categoryToVeranstalterMap[$setting->category] = $veranstalter->IDBenutzer;
                        break 2;
                    }
                }
            }
        }
        
        // If no match found from module numbers, check if there's a direct link in settings
        if (!isset($categoryToVeranstalterMap[$setting->category]) && !empty($setting->veranstalter_id)) {
            $categoryToVeranstalterMap[$setting->category] = $setting->veranstalter_id;
        }
    }
    
    // Process each category
    $processedCount = 0;
    $errorCount = 0;
    
    foreach ($categoryIds as $categoryId) {
        try {
            $category = \local_eventocoursecreation\EventoCourseCategory::get($categoryId);
            
            // Skip if no Veranstalter mapping found
            if (!isset($categoryToVeranstalterMap[$categoryId])) {
                $logger->warning("No Veranstalter mapping found for category {$category->getName()}");
                continue;
            }
            
            $veranstalterId = $categoryToVeranstalterMap[$categoryId];
            
            $logger->info("Processing category {$category->getName()} with Veranstalter ID: {$veranstalterId}");
            
            // Get the category settings
            $catSettings = \local_eventocoursecreation_setting::get($category->getId());
            
            // Determine fetching mode
            $globalFetchingMode = get_config('local_eventocoursecreation', 'fetching_mode') ?: 'classic';
            $fetchingMode = $catSettings->override_global_fetching && !empty($catSettings->fetching_mode)
                ? $catSettings->fetching_mode
                : $globalFetchingMode;
            
            // Process using reflection to call the private method
            $serviceReflection = new ReflectionObject($service);
            
            // Call initializeEventFetcher first
            $initMethod = $serviceReflection->getMethod('initializeEventFetcher');
            $initMethod->setAccessible(true);
            $initMethod->invoke($service, $category);
            
            // Now call the appropriate processing method
            switch ($fetchingMode) {
                case 'fast':
                    $method = $serviceReflection->getMethod('processCategoryWithFastMode');
                    break;
                case 'smart':
                    $method = $serviceReflection->getMethod('processCategoryWithSmartMode');
                    break;
                case 'parallel':
                    if (PHP_SAPI === 'cli') {
                        $method = $serviceReflection->getMethod('processCategoryWithParallelMode');
                    } else {
                        $logger->error("Parallel mode requires CLI. Falling back to smart mode.");
                        $method = $serviceReflection->getMethod('processCategoryWithSmartMode');
                    }
                    break;
                case 'classic':
                default:
                    $method = $serviceReflection->getMethod('processCategoryWithClassicMode');
            }
            
            $method->setAccessible(true);
            $method->invoke($service, $category, $veranstalterId);
            
            $processedCount++;
            
        } catch (Exception $e) {
            $logger->error("Error processing category {$categoryId}: " . $e->getMessage());
            $errorCount++;
        }
    }
    
    $logger->info("Category processing complete. Processed: {$processedCount}, Errors: {$errorCount}");
    
    if ($errorCount > 0) {
        return 1;
    } else if ($processedCount === 0) {
        return 2;
    } else {
        return 0;
    }
}