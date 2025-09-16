<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Logger\LoggerInterface;

/**
 * S3 stream wrapper with local index support.
 * 
 * This class registers a custom S3 stream wrapper that uses local indexes
 * for fast file operations. It replaces the default S3 wrapper to provide
 * better performance for file existence checks and directory listings.
 */
class Wrapper implements WrapperInterface
{
    public $context; // Required for PHP stream wrapper

    private static bool $registered = false;
    private static ?Wrapper $instance = null;

    private static ReaderInterface $reader;
    private static DirectoryInterface $directory;
    private static LoggerInterface $logger;
    
    /**
     * Keep a reference to the original wrapper under a backup name
     */
    private const ORIGINAL_PROTOCOL = 's3';
    private const BACKUP_PROTOCOL = 's3_backup';
    
    /**
     * Handle for fallback stream operations
     */
    private $fallbackHandle = null;
    
    /**
     * Handle for fallback directory operations
     */
    private $fallbackDirHandle = null;
    
    /**
     * Parameterless constructor required by PHP stream wrapper system.
     */
    public function __construct()
    {
    }

    /**
     * Set dependencies statically.
     *
     * @param ReaderInterface    $reader    Stream reader for file operations
     * @param DirectoryInterface $directory Directory handler for directory operations
     * @param LoggerInterface    $logger    Logger for debug messages
     */
    public static function setDependencies(ReaderInterface $reader, DirectoryInterface $directory, LoggerInterface $logger): void
    {
        self::$reader = $reader;
        self::$directory = $directory;
        self::$logger = $logger;
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
     * This method checks for S3_Uploads plugin availability, backs up the
     * original S3 wrapper, and registers this custom wrapper for fallback support.
     * 
     * @return void
     */
    public function init(): void
    {
        if (!class_exists('S3_Uploads\Plugin')) {
            self::$logger->log('[S3 Local Index] S3_Uploads plugin not found, wrapper not registered.');
            return;
        }

        if (!self::$registered) {
            // Store instance for static method access
            self::setInstance($this);
          
            // Backup original S3 wrapper
            if (!in_array(self::BACKUP_PROTOCOL, stream_get_wrappers(), true)) {
                if (in_array(self::ORIGINAL_PROTOCOL, stream_get_wrappers(), true)) {
                    @stream_wrapper_unregister(self::ORIGINAL_PROTOCOL);
                    // The original S3 wrapper class is likely named 'S3'
                    if (!@stream_wrapper_register(self::BACKUP_PROTOCOL, 'S3')) {
                        self::$logger->log('[S3 Local Index] Failed to register original S3 wrapper for fallback.');
                    } else {
                        self::$logger->log('[S3 Local Index] Original S3 wrapper backed up as ' . self::BACKUP_PROTOCOL . '://');
                    }
                } else {
                  self::$logger->log('[S3 Local Index] No existing S3 wrapper found to backup.');
                }
            }

            // Register custom wrapper
            if (!stream_wrapper_register(self::ORIGINAL_PROTOCOL, self::class)) {
                self::$logger->log('[S3 Local Index] Failed to register stream wrapper.');
                return;
            }

            self::$registered = true;

            self::$logger->log('[S3 Local Index] Stream wrapper registered.');
        }
    }

    /* Forward stream wrapper calls to Reader / Directory */

    /**
     * Stream wrapper: Open file or URL.
     * 
     * Attempts to open via local index first, falls back to original wrapper.
     * 
     * @param  string      $path        The file or URL to open
     * @param  string      $mode        The file mode
     * @param  int         $options     Options bitmask
     * @param  string|null $opened_path The full path actually opened
     * @return bool True on success, false on failure
     */
    public function stream_open($path, $mode, $options, &$opened_path)
    {
        $result = self::$reader->stream_open($path, $mode, $options, $opened_path);
        
        // If our reader successfully opened the stream, use it
        if ($result) {
            return true;
        }
        
        // Fallback to original S3 wrapper
        $fallbackPath = preg_replace('#^' . self::ORIGINAL_PROTOCOL . '://#', self::BACKUP_PROTOCOL . '://', $path);
        $fallbackHandle = @fopen($fallbackPath, $mode);
        
        if ($fallbackHandle) {
            // Store the handle for later use in stream operations
            $this->fallbackHandle = $fallbackHandle;
            $opened_path = $fallbackPath;
            return true;
        }
        
        return false;
    }

    /**
     * Stream wrapper: Read from stream.
     * 
     * Reads from local index stream or fallback handle.
     * 
     * @param  int $count Maximum number of bytes to read
     * @return string Data read from stream
     */
    public function stream_read($count)
    {
        // If we have a fallback handle, use it
        if ($this->fallbackHandle) {
            return fread($this->fallbackHandle, $count);
        }
        
        // Otherwise use our reader
        return self::$reader->stream_read($count);
    }

    /**
     * Stream wrapper: Test for end-of-file on stream.
     * 
     * Tests EOF on local index stream or fallback handle.
     * 
     * @return bool True if end-of-file reached, false otherwise
     */
    public function stream_eof()
    {
        // If we have a fallback handle, use it
        if ($this->fallbackHandle) {
            return feof($this->fallbackHandle);
        }
        
        // Otherwise use our reader
        return self::$reader->stream_eof();
    }

    /**
     * Stream wrapper: Close stream.
     * 
     * @return void
     */
    public function stream_close()
    {
        if ($this->fallbackHandle) {
            fclose($this->fallbackHandle);
            $this->fallbackHandle = null;
        }
    }

    /**
     * Stream wrapper: Retrieve information about a file.
     * 
     * Uses index for real files that exist in our cache,
     * falls back to original wrapper otherwise.
     * 
     * @param  string $path  The file path to stat
     * @param  int    $flags Flags
     * @return array|false File statistics or false on failure
     */
    public function url_stat($path, $flags)
    {
        $result = self::$reader->url_stat($path, $flags);
        
        // If our reader found the file in the index, return that result
        if ($result !== false) {
            return $result;
        }
        
        // Fallback to original S3 wrapper
        $fallbackPath = preg_replace('#^' . self::ORIGINAL_PROTOCOL . '://#', self::BACKUP_PROTOCOL . '://', $path);
        return @stat($fallbackPath);
    }

    /**
     * Stream wrapper: Open directory for reading.
     * 
     * Attempts to open via local index first, falls back to original wrapper.
     * 
     * @param  string $path    The directory path to open
     * @param  int    $options Options
     * @return bool True on success, false on failure
     */
    public function dir_opendir($path, $options)
    {
        $result = self::$directory->dir_opendir($path, $options);
        
        // If our directory handler successfully opened, use it
        if ($result) {
            return true;
        }
        
        // Fallback to original S3 wrapper
        $fallbackPath = preg_replace('#^' . self::ORIGINAL_PROTOCOL . '://#', self::BACKUP_PROTOCOL . '://', $path);
        $fallbackHandle = @opendir($fallbackPath);
        
        if ($fallbackHandle) {
            $this->fallbackDirHandle = $fallbackHandle;
            return true;
        }
        
        return false;
    }

    /**
     * Stream wrapper: Read entry from directory.
     * 
     * Reads from local index directory or fallback handle.
     * 
     * @return string|false Next filename or false when done
     */
    public function dir_readdir()
    {
        // If we have a fallback directory handle, use it
        if ($this->fallbackDirHandle) {
            return readdir($this->fallbackDirHandle);
        }
        
        // Otherwise use our directory handler
        return self::$directory->dir_readdir();
    }

    /**
     * Stream wrapper: Close directory handle.
     * 
     * @return void
     */
    public function dir_closedir()
    {
        if ($this->fallbackDirHandle) {
            closedir($this->fallbackDirHandle);
            $this->fallbackDirHandle = null;
        } else {
            self::$directory->dir_closedir();
        }
    }
}