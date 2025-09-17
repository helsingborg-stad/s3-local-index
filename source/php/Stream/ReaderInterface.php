<?php

namespace S3_Local_Index\Stream;

/**
 * Interface for S3 stream readers with local index support.
 */
interface ReaderInterface
{
    /**
     * Extract index details from a file path.
     *
     * @param  string $path S3 file path
     * @return array|null Array with blogId, year, month or null if path doesn't match pattern
     */
    public function extractIndexDetails(string $path): ?array;

    /**
     * Flush cache for a specific file path.
     *
     * @param  string $path S3 file path
     * @return bool True if cache was flushed, false if path doesn't match pattern
     */
    public function flushCacheForPath(string $path): bool;

    /**
     * Get cache key for a specific file path.
     *
     * @param  string $path S3 file path
     * @return string|null Cache key or null if path doesn't match pattern
     */
    public function getCacheKeyForPath(string $path): ?string;

    /**
     * Load index data for a given path from cache or file system.
     *
     * @param  string $path S3 file path to load index for
     * @return array Index data containing file paths
     */
    public function loadIndex(string $path): array;

    /**
     * Get file statistics.
     *
     * @param  string $path  Path to stat
     * @param  int    $flags Stat flags
     * @return array|false File statistics or false if file doesn't exist
     */
    public function url_stat(string $path, int $flags) : array|false;

    /**
     * Normalize a path by removing protocol and leading slashes.
     *
     * @param  string $path Path to normalize
     * @return string Normalized path
     */
    public function normalize(string $path): string;
}