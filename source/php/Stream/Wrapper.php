<?php

namespace S3_Local_Index\Stream;

class Wrapper {

    private static bool $registered = false;
    private Reader $reader;
    private Directory $directory;

    public function __construct() {
        $this->reader = new Reader();
        $this->directory = new Directory();
    }

    public static function init(): void {
        if (!class_exists('S3_Uploads\Plugin')) {
            error_log('[S3 Local Index] S3_Uploads plugin not found, wrapper not registered.');
            return;
        }

        if (!self::$registered) {
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
        return $this->reader->stream_open($path, $mode, $options, $opened_path);
    }

    public function stream_read($count) {
        return $this->reader->stream_read($count);
    }

    public function stream_eof() {
        return $this->reader->stream_eof();
    }

    public function url_stat($path, $flags) {
        return $this->reader->url_stat($path, $flags);
    }

    public function dir_opendir($path, $options) {
        return $this->directory->dir_opendir($path, $options);
    }

    public function dir_readdir() {
        return $this->directory->dir_readdir();
    }

    public function dir_closedir() {
        return $this->directory->dir_closedir();
    }
}