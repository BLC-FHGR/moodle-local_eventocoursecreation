<?php

namespace local_eventocoursecreation\api;

/**
 * Configuration manager for event fetchers
 * 
 * Provides a unified configuration interface with smart defaults and overrides
 * 
 * @package    local_eventocoursecreation
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class FetcherConfiguration {
    /**
     * Default configuration values
     * 
     * @var array 
     */
    private static $defaultConfig = [
        // General settings
        'fetching_mode' => 'smart',          // Default mode: classic, smart, fast, parallel
        'enable_recovery' => true,           // Whether to attempt recovery for failed fetches
        
        // Batch processing
        'batch_size' => 200,                 // Default batch size
        'min_batch_size' => 10,              // Minimum batch size in case of errors
        'max_batch_size' => 1000,            // Maximum batch size to try
        'adaptive_batch_sizing' => true,     // Dynamically adjust batch size
        
        // Parallel processing
        'num_threads' => 2,                  // Number of worker processes for parallel mode
        'task_timeout' => 300,               // Max seconds for a task to complete
        'chunk_size' => 50,                  // Size of chunks for each worker
        
        // Fallback strategies
        'date_chunk_fallback' => true,       // Fall back to date chunking
        'date_chunk_days' => 90,             // Size of date chunks in days
        
        // Error handling
        'max_api_retries' => 3,              // Max retries for API calls
        'retry_delay_base' => 1,             // Base seconds for retry delay (exponential backoff)
        'error_threshold' => 5,              // Threshold for switching strategies
        
        // Caching
        'cache_ttl' => 3600,                 // Cache TTL in seconds
        
        // Feature flags
        'enable_incremental' => true,        // Enable incremental fetching
        'parallel_requests' => false,        // Enable parallel requests (experimental)
        'max_parallel_threads' => 2,         // Maximum parallel requests if enabled
    ];
    
    /**
     * Current configuration
     * 
     * @var array 
     */
    private $config;
    
    /**
     * Constructor
     * 
     * @param array $overrides Manual configuration overrides
     */
    public function __construct(array $overrides = []) {
        // Load global settings from Moodle configuration
        $globalConfig = $this->loadGlobalConfig();
        
        // Apply global settings, then overrides
        $this->config = array_merge(self::$defaultConfig, $globalConfig, $overrides);
    }
    
    /**
     * Get a specific configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public function get(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Get all configuration values
     * 
     * @return array All configuration values
     */
    public function getAll(): array {
        return $this->config;
    }
    
    /**
     * Create a new configuration with overrides
     * 
     * @param array $overrides New overrides to apply
     * @return FetcherConfiguration New configuration instance
     */
    public function withOverrides(array $overrides): FetcherConfiguration {
        $newConfig = new self();
        $newConfig->config = array_merge($this->config, $overrides);
        return $newConfig;
    }
    
    /**
     * Load global configuration from Moodle settings
     * 
     * @return array Global configuration values
     */
    private function loadGlobalConfig(): array {
        $globalConfig = [];
        
        // Map plugin config to our configuration
        $configMappings = [
            'fetching_mode' => 'fetching_mode',
            'batch_size' => 'batch_size',
            'min_batch_size' => 'min_batch_size',
            'max_batch_size' => 'max_batch_size',
            'adaptive_batch_sizing' => 'adaptive_batch_sizing',
            'date_chunk_fallback' => 'date_chunk_fallback',
            'date_chunk_days' => 'date_chunk_days',
            'max_api_retries' => 'max_api_retries',
            'cache_ttl' => 'cache_ttl',
            'max_parallel_threads' => 'max_parallel_threads',
            'enable_recovery' => 'enable_recovery'
        ];
        
        // Load from Moodle configuration
        foreach ($configMappings as $ourKey => $moodleKey) {
            $value = get_config('local_eventocoursecreation', $moodleKey);
            if ($value !== false) { // Check if setting exists
                // Convert value to appropriate type
                if (is_numeric($value)) {
                    $value = (int)$value;
                } else if ($value === '0' || $value === '1' || $value === 'true' || $value === 'false') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                $globalConfig[$ourKey] = $value;
            }
        }
        
        // Special handling for mode-dependent settings
        $fetchingMode = get_config('local_eventocoursecreation', 'fetching_mode') ?: 'classic';
        $globalConfig['enable_incremental'] = in_array($fetchingMode, ['fast', 'smart']);
        
        return $globalConfig;
    }
}