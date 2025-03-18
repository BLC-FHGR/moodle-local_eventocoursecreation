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
 * Evento cache maintenance task
 *
 * @package    local_eventocoursecreation
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventocoursecreation\task;

defined('MOODLE_INTERNAL') || die();

// DateTime format for SOAP requests if not already defined
if (!defined('LOCAL_EVENTO_DATETIME_FORMAT')) {
    define('LOCAL_EVENTO_DATETIME_FORMAT', "Y-m-d\TH:i:s.uP");
}

use core\task\scheduled_task;
use local_eventocoursecreation\api\EventoApiCache;
use local_eventocoursecreation\api\SmartEventFetcher;

/**
 * Task to maintain the event cache
 */
class evento_cache_maintenance_task extends scheduled_task {

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('eventocachemaintenance', 'local_eventocoursecreation');
    }
    
    /**
     * Execute the task
     */
    public function execute() {
        global $CFG;
        // FIXED: Use the correct locallib.php file
        require_once($CFG->dirroot . '/local/eventocoursecreation/locallib.php');
        
        mtrace('Starting Evento cache maintenance...');
        
        $cache = new EventoApiCache();
        
        // Check if we should perform a full purge
        $lastFullPurge = get_config('local_eventocoursecreation', 'last_full_cache_purge');
        $now = time();
        
        // Perform full purge once a week
        if (empty($lastFullPurge) || ($now - $lastFullPurge) > 7*24*60*60) {
            mtrace('Performing full cache purge...');
            $cache->purge();
            set_config('last_full_cache_purge', $now, 'local_eventocoursecreation');
            mtrace('Cache purged successfully');
        } else {
            mtrace('Full cache purge not needed yet');
        }
        
        // Force refresh of upcoming events to ensure we have the latest data
        mtrace('Refreshing cache for upcoming events...');
        
        $eventoService = new \local_evento_evento_service();
        
        if (!$eventoService->init_call()) {
            mtrace('Failed to initialize Evento connection');
            return;
        }
        
        // Get active Veranstalter list
        $veranstalterList = $eventoService->get_active_veranstalter();
        if (empty($veranstalterList)) {
            mtrace('No active Veranstalter found');
            return;
        }
        
        // Create event fetcher
        $fetcherConfig = [
            'batch_size' => (int)get_config('local_eventocoursecreation', 'batch_size') ?: 200,
            'min_batch_size' => (int)get_config('local_eventocoursecreation', 'min_batch_size') ?: 10,
            'max_batch_size' => (int)get_config('local_eventocoursecreation', 'max_batch_size') ?: 1000,
            'adaptive_batch_sizing' => (bool)get_config('local_eventocoursecreation', 'adaptive_batch_sizing'),
            'date_chunk_fallback' => (bool)get_config('local_eventocoursecreation', 'date_chunk_fallback'),
            'date_chunk_days' => (int)get_config('local_eventocoursecreation', 'date_chunk_days') ?: 90,
            'max_api_retries' => (int)get_config('local_eventocoursecreation', 'max_api_retries') ?: 3,
            'cache_ttl' => (int)get_config('local_eventocoursecreation', 'cache_ttl') ?: 3600,
            'enable_incremental' => true,
        ];
        $eventFetcher = new SmartEventFetcher(
            $eventoService, 
            $cache, 
            new \null_progress_trace(), 
            $fetcherConfig
        );
        
        // Update cache for each active Veranstalter
        foreach ($veranstalterList as $veranstalter) {
            if (empty($veranstalter->IDBenutzer)) {
                continue;
            }
            
            mtrace("Refreshing cache for {$veranstalter->benutzerName}...");
            
            try {
                // Only update if there are events
                $fromDate = new \DateTime(date('Y-m-d')); // Today
                $toDate = new \DateTime('+1 year'); // 1 year from now
                
                $events = $eventFetcher->fetchAllEvents(
                    $veranstalter->IDBenutzer, 
                    $fromDate, 
                    $toDate, 
                    true // Force refresh
                );
                
                mtrace("Updated cache with {$veranstalter->benutzerName} ({$veranstalter->IDBenutzer}): " . count($events) . " events");
                
                // Save stats
                $stats = $eventFetcher->getStats();
                $this->saveStats($stats, $veranstalter->IDBenutzer);
                
            } catch (\Exception $e) {
                mtrace("Error refreshing cache for {$veranstalter->benutzerName}: " . $e->getMessage());
            }
        }
        
        mtrace('Cache maintenance completed');
    }
    
    /**
     * Save stats to cache for monitoring
     * 
     * @param array $stats The statistics
     * @param string $veranstalterId The Veranstalter ID
     */
    private function saveStats(array $stats, string $veranstalterId): void {
        $cache = new EventoApiCache();
        
        // Get existing stats
        $allStats = $cache->get('api_stats') ?: [];
        
        // Update stats for this Veranstalter
        $stats['last_run'] = time();
        $allStats[$veranstalterId] = $stats;
        
        // Calculate totals
        $totals = [
            'api_calls' => 0,
            'errors' => 0,
            'cache_hits' => 0,
            'last_run' => time()
        ];
        
        foreach ($allStats as $verStats) {
            $totals['api_calls'] += $verStats['api_calls'] ?? 0;
            $totals['errors'] += $verStats['errors'] ?? 0;
            $totals['cache_hits'] += $verStats['cache_hits'] ?? 0;
        }
        
        $allStats['totals'] = $totals;
        
        // Save to cache
        $cache->set('api_stats', $allStats);
    }
}