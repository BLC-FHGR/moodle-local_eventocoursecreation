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
 * Lock helper for parallel event fetching
 *
 * @package    local_eventocoursecreation
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_eventocoursecreation\util;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for working with Moodle locks
 */
class lock_helper {
    
    /**
     * Get a lock factory
     *
     * @param string $type The lock factory type
     * @return \core\lock\lock_factory
     */
    public static function get_lock_factory($type) {
        global $CFG;
        require_once($CFG->libdir . '/locklib.php');
        
        return new \core\lock\lock_factory($type);
    }
    
    /**
     * Acquire a lock with careful error handling
     *
     * @param string $type Lock factory type
     * @param string $resource Resource identifier to lock
     * @param int $timeout Timeout in seconds
     * @param int $maxlifetime Maximum lifetime in seconds
     * @return \core\lock\lock|false Lock object or false if could not acquire
     */
    public static function get_lock($type, $resource, $timeout, $maxlifetime = 86400) {
        $factory = self::get_lock_factory($type);
        
        if (!$factory->is_available()) {
            debugging('Lock factory is not available: ' . $type, DEBUG_DEVELOPER);
            return false;
        }
        
        return $factory->get_lock($resource, $timeout, $maxlifetime);
    }
    
    /**
     * Release a lock
     *
     * @param \core\lock\lock $lock The lock to release
     * @return bool Success
     */
    public static function release_lock(\core\lock\lock $lock) {
        return $lock->release();
    }
    
    /**
     * Execute code while holding a lock, ensuring the lock is released
     *
     * @param string $type Lock factory type
     * @param string $resource Resource identifier to lock
     * @param callable $callback Function to execute with lock
     * @param int $timeout Timeout in seconds
     * @param int $maxlifetime Maximum lifetime in seconds
     * @return mixed|false Result of callback or false if lock could not be acquired
     */
    public static function with_lock($type, $resource, callable $callback, $timeout = 5, $maxlifetime = 86400) {
        $lock = self::get_lock($type, $resource, $timeout, $maxlifetime);
        
        if ($lock === false) {
            return false;
        }
        
        try {
            return $callback($lock);
        } finally {
            self::release_lock($lock);
        }
    }
}