<?php

namespace S3_Local_Index\Logger;

/**
 * Debug-aware logger implementation.
 * 
 * This logger checks for the WP_DEBUG constant and only writes
 * log messages when debugging is enabled, suppressing output
 * when debug is disabled.
 */
class Logger implements LoggerInterface
{
    private const LOG_TAG = '[S3 Local Index] ';

    /**
     * Log a message if debug is enabled.
     * 
     * Checks the WP_DEBUG constant and only calls error_log()
     * if debugging is enabled. This prevents log file pollution
     * in production environments.
     * 
     * @param string $message The message to log
     * @return void
     */
    public function log(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(self::LOG_TAG . $message);
        }
    }
}