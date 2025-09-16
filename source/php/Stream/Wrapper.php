<?php

namespace S3_Local_Index\Stream;

/**
 * S3 stream wrapper with local index support.
 * 
 * This class registers a custom S3 stream wrapper that uses local indexes
 * for fast file operations. It replaces the default S3 wrapper to provide
 * better performance for file existence checks and directory listings.
 */
class Wrapper
{

    private static bool $registered = false;
    private static ?Wrapper $instance = null;
    
    /**
     * Constructor with dependency injection
     *
     * @param ReaderInterface    $reader    Stream reader for file operations
     * @param DirectoryInterface $directory Directory handler for directory operations
     */
    public function __construct(
        private ReaderInterface $reader,
        private DirectoryInterface $directory
    ) {
    }

    /**
     * Set the singleton instance
     * 
     * @param  Wrapper $instance The wrapper instance to store
     * @return void
     */
    public static function setInstance(?Wrapper $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Get the singleton instance
     * 
     * @return Wrapper|null The stored wrapper instance or null if not set
     */
    public static function getInstance(): ?Wrapper
    {
        return self::$instance;
    }

    /**
     * Initialize and register the S3 stream wrapper.
     * 
     * This method checks for S3_Uploads plugin availability, unregisters
     * any existing S3 wrapper, and registers this custom wrapper.
     * 
     * @return void
     */
    public function init(): void
    {
        if (!class_exists('S3_Uploads\Plugin')) {
            error_log('[S3 Local Index] S3_Uploads plugin not found, wrapper not registered.');
            return;
        }

        if (!self::$registered) {
            // Store instance for static method access
            self::setInstance($this);
            
            if (in_array('s3', stream_get_wrappers(), true)) {
                @stream_wrapper_unregister('s3');
                error_log('[S3 Local Index] Existing s3 wrapper unregistered.');
            }

            if (!stream_wrapper_register('s3', self::class)) {
                error_log('[S3 Local Index] Failed to register stream wrapper.');
                return;
            }

            self::$registered = true;
            error_log('[S3 Local Index] Stream wrapper registered.');
        }
    }

    /* Forward stream wrapper calls to Reader / Directory */

    /**
     * Stream wrapper: Open file or URL.
     * 
     * @param  string      $path        The file or URL to open
     * @param  string      $mode        The file mode
     * @param  int         $options     Options bitmask
     * @param  string|null $opened_path The full path actually opened
     * @return bool True on success, false on failure
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $instance = self::getInstance();
        return $instance ? $instance->reader->stream_open($path, $mode, $options, $opened_path) : false;
    }

    /**
     * Stream wrapper: Read from stream.
     * 
     * @param  int $count Maximum number of bytes to read
     * @return string Data read from stream
     */
    public function stream_read($count)
    {
        $instance = self::getInstance();
        return $instance ? $instance->reader->stream_read($count) : '';
    }

    /**
     * Stream wrapper: Test for end-of-file on stream.
     * 
     * @return bool True if end-of-file reached, false otherwise
     */
    public function stream_eof()
    {
        $instance = self::getInstance();
        return $instance ? $instance->reader->stream_eof() : true;
    }

    /**
     * Stream wrapper: Retrieve information about a file.
     * 
     * @param  string $path  The file path to stat
     * @param  int    $flags Flags
     * @return array|false File statistics or false on failure
     */
    public function url_stat($path, $flags)
    {
        $instance = self::getInstance();
        return $instance ? $instance->reader->url_stat($path, $flags) : false;
    }

    /**
     * Stream wrapper: Open directory for reading.
     * 
     * @param  string $path    The directory path to open
     * @param  int    $options Options
     * @return bool True on success, false on failure
     */
    public function dir_opendir($path, $options)
    {
        $instance = self::getInstance();
        return $instance ? $instance->directory->dir_opendir($path, $options) : false;
    }

    /**
     * Stream wrapper: Read entry from directory.
     * 
     * @return string|false Next filename or false when done
     */
    public function dir_readdir()
    {
        $instance = self::getInstance();
        return $instance ? $instance->directory->dir_readdir() : false;
    }

    /**
     * Stream wrapper: Close directory handle.
     * 
     * @return void
     */
    public function dir_closedir()
    {
        $instance = self::getInstance();
        if ($instance) {
            $instance->directory->dir_closedir();
        }
    }
}