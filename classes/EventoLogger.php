<?php

namespace local_eventocoursecreation;

use progress_trace;

/**
 * Enhanced logging capabilities
 */
class EventoLogger
{
    /**
     * @var progress_trace The trace object for logging
     */
    private progress_trace $trace;

    /**
     * Constructor
     *
     * @param progress_trace $trace
     */
    public function __construct(progress_trace $trace)
    {
        $this->trace = $trace;
    }

    /**
     * Logs an info message
     *
     * @param string $message
     * @param array $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    /**
     * Logs an error message
     *
     * @param string $message
     * @param array $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    /**
     * Logs a debug message if debugging is enabled
     *
     * @param string $message
     * @param array $context
     */
    public function debug(string $message, array $context = []): void
    {
        if (debugging('', DEBUG_DEVELOPER)) {
            $this->log('DEBUG', $message, $context);
        }
    }

    /**
     * Internal logging method
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log(string $level, string $message, array $context): void
    {
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $this->trace->output(sprintf(
            '[%s] [%s] %s%s',
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $contextStr
        ));
    }
}
