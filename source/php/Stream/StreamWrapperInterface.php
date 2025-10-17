<?php

namespace S3_Local_Index\Stream;

/**
 * Interface for S3 stream wrapper with local index support.
 *
 * Defines the required methods for a stream wrapper implementation.
 */
interface StreamWrapperInterface
{
    /**
     * Wrapper method to add index lookup to url_stat.
     *
     * This method first checks the local index for file existence
     * before delegating to the original S3 stream wrapper.
     *
     * @param string $path  The file URI to check.
     * @param int    $flags Flags for the stat operation.
     *
     * @return array|false File statistics array or false if the file doesn't exist.
     */
    public function url_stat(string $path, int $flags);
}