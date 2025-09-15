<?php

namespace S3_Local_Index\Stream;

class Wrapper {

    private static bool $registered = false;
    private static ?Wrapper $instance = null;
    
    /**
     * Constructor with dependency injection
     *
     * @param Reader $reader
     * @param Directory $directory
     */
    public function __construct(
        private Reader $reader,
        private Directory $directory
    ) {
    }

    /**
     * Set the singleton instance
     */
    public static function setInstance(Wrapper $instance): void {
        self::$instance = $instance;
    }

    /**
     * Get the singleton instance
     */
    public static function getInstance(): ?Wrapper {
        return self::$instance;
    }

    public function init(): void {
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

    public function stream_open($path, $mode, $options, &$opened_path) {
        $instance = self::getInstance();
        return $instance ? $instance->reader->stream_open($path, $mode, $options, $opened_path) : false;
    }

    public function stream_read($count) {
        $instance = self::getInstance();
        return $instance ? $instance->reader->stream_read($count) : '';
    }

    public function stream_eof() {
        $instance = self::getInstance();
        return $instance ? $instance->reader->stream_eof() : true;
    }

    public function url_stat($path, $flags) {
        $instance = self::getInstance();
        return $instance ? $instance->reader->url_stat($path, $flags) : false;
    }

    public function dir_opendir($path, $options) {
        $instance = self::getInstance();
        return $instance ? $instance->directory->dir_opendir($path, $options) : false;
    }

    public function dir_readdir() {
        $instance = self::getInstance();
        return $instance ? $instance->directory->dir_readdir() : false;
    }

    public function dir_closedir() {
        $instance = self::getInstance();
        if ($instance) {
            $instance->directory->dir_closedir();
        }
    }
}