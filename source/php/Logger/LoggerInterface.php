<?php

namespace S3_Local_Index\Logger;

/**
 * Interface for logging functionality.
 * 
 * Provides a contract for logging implementations that can
 * conditionally write log messages based on debug settings.
 */
interface LoggerInterface
{
    /**
     * Log a message if debug is enabled.
     * 
     * @param  string $message The message to log
     * @return void
     */
    public function log(string $message): void;
}