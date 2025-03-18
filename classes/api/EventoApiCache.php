<?php

namespace local_eventocoursecreation\api;

/**
 * Cache manager for Evento API data
 * 
 * Provides a standardized interface for caching API responses
 * with configurable TTL and namespacing
 * 
 * @package    local_eventocoursecreation
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class EventoApiCache {
    /** @var \cache_application */
    private $cache;
    
    /** @var int Default cache TTL in seconds */
    private $defaultTtl;
    
    /** @var string */
    private $prefix;
    
    /**
     * Constructor
     * 
     * @param string $prefix Optional cache key prefix
     */
    public function __construct(string $prefix = 'evento_api_') {
        // Use the existing cache definition instead of trying to create a new one
        $this->cache = \cache::make('local_eventocoursecreation', 'eventocreation_api');
        $this->prefix = $prefix;
        
        // Get default TTL from config
        $this->defaultTtl = (int)get_config('local_eventocoursecreation', 'cache_ttl') ?: 3600;
    }
    
    /**
     * Get a value from cache
     * 
     * @param string $key Cache key
     * @return mixed|false The cached value or false if not found
     */
    public function get(string $key) {
        $key = $this->prefix . $key;
        $data = $this->cache->get($key);
        
        if ($data === false) {
            return false;
        }
        
        // Check if the data has expired
        if (isset($data['expires']) && $data['expires'] < time()) {
            $this->delete($key);
            return false;
        }
        
        return $data['value'] ?? false;
    }
    
    /**
     * Set a value in cache with optional TTL
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Optional TTL in seconds (null for default)
     * @return bool Success
     */
    public function set(string $key, $value, ?int $ttl = null): bool {
        $key = $this->prefix . $key;
        
        // Use default TTL if not specified
        $ttl = $ttl ?? $this->defaultTtl;
        
        // Store value with expiration timestamp
        $data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return $this->cache->set($key, $data);
    }
    
    /**
     * Delete a value from cache
     * 
     * @param string $key Cache key
     * @return bool Success
     */
    public function delete(string $key): bool {
        $key = $this->prefix . $key;
        return $this->cache->delete($key);
    }
    
    /**
     * Check if a key exists in cache and is not expired
     * 
     * @param string $key Cache key
     * @return bool Whether the key exists and is valid
     */
    public function has(string $key): bool {
        return $this->get($key) !== false;
    }
    
    /**
     * Set multiple values in cache at once
     * 
     * @param array $items Key-value pairs to cache
     * @param int|null $ttl Optional TTL in seconds (null for default)
     * @return bool Success
     */
    public function setMultiple(array $items, ?int $ttl = null): bool {
        $success = true;
        
        foreach ($items as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    /**
     * Get multiple values from cache at once
     * 
     * @param array $keys Keys to retrieve
     * @return array Key-value pairs of found items
     */
    public function getMultiple(array $keys): array {
        $result = [];
        
        foreach ($keys as $key) {
            $value = $this->get($key);
            if ($value !== false) {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Clear all cache entries with this prefix
     * 
     * @return bool Success
     */
    public function clear(): bool {
        // As we can't clear by prefix alone, we flush the entire cache
        // This is a limitation of Moodle's cache API
        return $this->cache->purge();
    }
    
    /**
     * Get or compute a value with an optional TTL
     * 
     * If the key exists in cache, returns the cached value
     * Otherwise, calls the callback, caches the result, and returns it
     * 
     * @param string $key Cache key
     * @param callable $callback Function to call if cache miss
     * @param int|null $ttl Optional TTL in seconds (null for default)
     * @return mixed The cached or computed value
     */
    public function getOrCompute(string $key, callable $callback, ?int $ttl = null) {
        $value = $this->get($key);
        
        if ($value !== false) {
            return $value;
        }
        
        // Cache miss, compute the value
        $value = $callback();
        
        // Cache the result
        $this->set($key, $value, $ttl);
        
        return $value;
    }
}