<?php

namespace S3_Local_Index\Stream;

/**
 * Interface for S3 stream readers with local index support.
 */
interface ReaderInterface
{
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
     * @return array|string File statistics or 'not_found' if file doesn't exist 'no_index' if no index found
     */
    public function url_stat(string $path, int $flags) : string|array;

    /**
     * Normalize a path by removing protocol and leading slashes.
     *
     * @param  string $path Path to normalize
     * @return string Normalized path
     */
    public function normalize(string $path): string;

    /**
     * Update the local index with a new file path.
     *
     * @param string $path S3 file path
     * @return bool True if updated, false if path is invalid
     */
    public function updateIndex(string $path): bool;

    /**
     * Remove a file path from the local index.
     *
     * @param string $path S3 file path
     * @return bool True if removed, false if path is invalid
     */
    public function removeFromIndex(string $path): bool;
}