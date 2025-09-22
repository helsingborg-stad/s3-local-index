<?php

namespace S3_Local_Index\Stream;

/**
 * Interface for S3 stream readers with local index support.
 */
interface ReaderInterface
{

    /**
     * Get file statistics.
     *
     * @param  string $path  Path to stat
     * @param  int    $flags Stat flags
     * @return array|string File statistics or 'not_found' if file doesn't exist 'no_index' if no index found
     */
    public function url_stat(string $path, int $flags) : string|array;
}