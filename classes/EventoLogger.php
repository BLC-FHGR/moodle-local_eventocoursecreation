<?php

namespace local_eventocoursecreation;

use progress_trace;

/**
 * Enhanced logger for Evento integration
 * 
 * Provides structured logging with context and different log levels
 * 
 * @package    local_eventocoursecreation
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class EventoLogger {
    /** @var progress_trace */
    private $trace;
    
    /** @var array */
    private $context = [];
    
    /** @var bool */
    private $debugEnabled = false;
    
    /**
     * Constructor
     * 
     * @param progress_trace $trace Trace for output
     * @param array $context Default context data
     */
    public function __construct(progress_trace $trace, array $context = []) {
        $this->trace = $trace;
        $this->context = $context;
        
        // Check if debug is enabled from config
        $this->debugEnabled = (bool)get_config('local_eventocoursecreation', 'enable_debug_logging');
    }
    
    /**
     * Create a new logger with additional context
     * 
     * @param array $context Context to add
     * @return EventoLogger New logger with merged context
     */
    public function withContext(array $context): EventoLogger {
        $logger = clone $this;
        $logger->context = array_merge($this->context, $context);
        return $logger;
    }
    
    /**
     * Log an info message
     * 
     * @param string $message Message to log
     * @param array $data Additional data for this message
     */
    public function info(string $message, array $data = []): void {
        $this->log('INFO', $message, $data);
    }
    
    /**
     * Log an error message
     * 
     * @param string $message Message to log
     * @param array $data Additional data for this message
     */
    public function error(string $message, array $data = []): void {
        $this->log('ERROR', $message, $data);
    }
    
    /**
     * Log a warning message
     * 
     * @param string $message Message to log
     * @param array $data Additional data for this message
     */
    public function warning(string $message, array $data = []): void {
        $this->log('WARNING', $message, $data);
    }
    
    /**
     * Log a debug message (only if debug is enabled)
     * 
     * @param string $message Message to log
     * @param array $data Additional data for this message
     */
    public function debug(string $message, array $data = []): void {
        if ($this->debugEnabled) {
            $this->log('DEBUG', $message, $data);
        }
    }
    
    /**
     * Format and output a log message
     * 
     * @param string $level Log level
     * @param string $message Message to log
     * @param array $data Additional data for this message
     */
    private function log(string $level, string $message, array $data): void {
        // Merge all context
        $context = array_merge($this->context, $data);
        
        // Format timestamp
        $timestamp = date('Y-m-d H:i:s');
        
        // Base message with timestamp and level
        $logMessage = "[$timestamp] [$level] $message";
        
        // Add context if present
        if (!empty($context)) {
            // Format context in a readable way
            $contextStr = $this->formatContext($context);
            $logMessage .= " | $contextStr";
        }
        
        // Output to trace
        $this->trace->output($logMessage);
    }
    
    /**
     * Format context data for logging
     * 
     * @param array $context Context to format
     * @return string Formatted context string
     */
    private function formatContext(array $context): string {
        $parts = [];
        
        foreach ($context as $key => $value) {
            // Handle different types of values
            if (is_array($value)) {
                // For arrays, use JSON
                $formatted = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                // Limit length for readability
                if (strlen($formatted) > 100) {
                    $formatted = substr($formatted, 0, 97) . '...';
                }
            } else if (is_object($value)) {
                // For objects, just show class name
                $formatted = get_class($value);
            } else if (is_bool($value)) {
                // Format booleans
                $formatted = $value ? 'true' : 'false';
            } else if (is_null($value)) {
                $formatted = 'null';
            } else {
                // Convert to string
                $formatted = (string)$value;
            }
            
            $parts[] = "$key=$formatted";
        }
        
        return implode(', ', $parts);
    }
    
    /**
     * Get the underlying trace object
     * 
     * @return progress_trace
     */
    public function getTrace(): progress_trace {
        return $this->trace;
    }
}