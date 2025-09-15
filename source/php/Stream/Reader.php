<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\FileSystem\FileSystemInterface;

/**
 * Stream reader for S3 files with local index support.
 * 
 * This class provides stream operations for S3 files using a local index
 * for fast file existence checks and metadata operations. It supports both
 * single-site and multisite WordPress configurations.
 */
class Reader {

    private array $index = [];
    private string $key = '';
    private int $position = 0;

    /**
     * Constructor with dependency injection
     *
     * @param CacheInterface $cache Cache interface for storing index data
     * @param FileSystemInterface $fileSystem File system interface for accessing index files
     */
    public function __construct(
        private CacheInterface $cache,
        private FileSystemInterface $fileSystem
    ) {
    }

    /**
     * Extract index details from a file path
     *
     * @param string $path S3 file path
     * @return array|null Array with blogId, year, month or null if path doesn't match pattern
     */
    public function extractIndexDetails(string $path): ?array {
        $path = ltrim($path, '/');
        
        // Try multisite pattern first
        if (preg_match('#uploads/networks/\d+/sites/(\d+)/(\d{4})/(\d{2})/#', $path, $m)) {
            return [
                'blogId' => $m[1],
                'year' => $m[2],
                'month' => $m[3]
            ];
        }
        
        // Try single site pattern
        if (preg_match('#uploads/(\d{4})/(\d{2})/#', $path, $m)) {
            return [
                'blogId' => '1',
                'year' => $m[1],
                'month' => $m[2]
            ];
        }
        
        return null;
    }

    /**
     * Flush cache for a specific file path
     *
     * @param string $path S3 file path
     * @return bool True if cache was flushed, false if path doesn't match pattern
     */
    public function flushCacheForPath(string $path): bool {
        $details = $this->extractIndexDetails($path);
        if ($details === null) {
            return false;
        }

        $cacheKey = "index_{$details['blogId']}_{$details['year']}_{$details['month']}";
        
        return $this->cache->delete($cacheKey);
    }

    /**
     * Get cache key for a specific file path
     *
     * @param string $path S3 file path
     * @return string|null Cache key or null if path doesn't match pattern
     */
    public function getCacheKeyForPath(string $path): ?string {
        $details = $this->extractIndexDetails($path);
        if ($details === null) {
            return null;
        }

        return "index_{$details['blogId']}_{$details['year']}_{$details['month']}";
    }

    /**
     * Load index data for a given path from cache or file system.
     * 
     * This method extracts blog ID, year, and month from the path and loads
     * the corresponding index file. It uses caching to improve performance.
     * 
     * @param string $path S3 file path to load index for
     * @return array Index data containing file paths
     */
    public function loadIndex(string $path): array {
        $path = ltrim($path, '/');
        if (!preg_match('#uploads(?:/networks/\d+/sites/(\d+))?/(\d{4})/(\d{2})/#', $path, $m)) {
            return [];
        }

        $blogId = $m[1] ?? '1';
        $year    = $m[2];
        $month   = $m[3];

        // Create cache key
        $cacheKey = "index_{$blogId}_{$year}_{$month}";
        
        // Try to get from cache first
        $cachedData = $this->cache->get($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }

        // Load from file if not in cache
        $file = $this->fileSystem->getCacheDir() . "/s3-index-{$blogId}-{$year}-{$month}.json";
        if (!$this->fileSystem->fileExists($file)) {
            return [];
        }

        $data = $this->fileSystem->fileGetContents($file);
        $index = json_decode($data, true) ?: [];
        
        // Store in cache for next time (cache for 1 hour)
        $this->cache->set($cacheKey, $index, 3600);
        
        return $index;
    }

    /**
     * Open a stream for reading.
     * 
     * Implementation of PHP's stream_open for the stream wrapper.
     * Loads the index and verifies the file exists before opening.
     * 
     * @param string $path Path to open
     * @param string $mode File mode (ignored for S3 streams)
     * @param int $options Stream options
     * @param string|null $opened_path Reference to the opened path
     * @return bool True if stream opened successfully, false otherwise
     */
    public function stream_open(string $path, string $mode, int $options, &$opened_path): bool {
        $this->index = $this->loadIndex($path);
        $normalized = $this->normalize($path);
        if (!isset($this->index[$normalized])) {
            return false;
        }

        $this->key = $normalized;
        $this->position = 0;
        return true;
    }

    /**
     * Read data from the stream.
     * 
     * Implementation of PHP's stream_read for the stream wrapper.
     * Reads data from the actual S3 file.
     * 
     * @param int $count Number of bytes to read
     * @return string Data read from the stream
     */
    public function stream_read(int $count): string {
        $data = file_get_contents('s3://' . $this->key);
        $chunk = substr($data, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    /**
     * Check if end of file has been reached.
     * 
     * Implementation of PHP's stream_eof for the stream wrapper.
     * 
     * @return bool True if at end of file, false otherwise
     */
    public function stream_eof(): bool {
        $data = file_get_contents('s3://' . $this->key);
        return $this->position >= strlen($data);
    }

    /**
     * Get file statistics.
     * 
     * Implementation of PHP's url_stat for the stream wrapper.
     * Returns basic file stats if the file exists in the index.
     * 
     * @param string $path Path to stat
     * @param int $flags Stat flags
     * @return array|false File statistics or false if file doesn't exist
     */
    public function url_stat(string $path, int $flags) {
        $this->index = $this->loadIndex($path);
        $normalized = $this->normalize($path);
        return isset($this->index[$normalized]) ? ['size' => 1, 'mtime' => time()] : false;
    }

    /**
     * Normalize a path by removing protocol and leading slashes.
     * 
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    private function normalize(string $path): string {
        return ltrim(str_replace('s3://', '', $path), '/');
    }
}