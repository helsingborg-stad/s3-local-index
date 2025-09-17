<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\Logger\LoggerInterface;

/**
 * Stream reader for S3 files with local index support.
 * 
 * This class provides stream operations for S3 files using a local index
 * for fast file existence checks and metadata operations. It supports both
 * single-site and multisite WordPress configurations.
 */
class Reader implements ReaderInterface
{
    /**
     * Constructor with dependency injection
     *
     * @param CacheInterface      $cache      Cache interface for storing index data
     * @param FileSystemInterface $fileSystem File system interface for accessing index files
     */
    public function __construct(
        private CacheInterface $cache,
        private FileSystemInterface $fileSystem,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Extract index details from a file path
     *
     * @param  string $path S3 file path
     * @return array|null Array with blogId, year, month or null if path doesn't match pattern
     */
    public function extractIndexDetails(string $path): ?array
    {
        $path = ltrim($path, '/');
        if (preg_match('#(?:uploads/networks/\d+/sites/(\d+)/)?(?:uploads/)?(\d{4})/(\d{2})/#', $path, $m)) {
            return [
                'blogId' => $m[1] ?? '1', // if multisite captured, use it; otherwise default to 1
                'year'   => $m[2],
                'month'  => $m[3],
            ];
        }
        
        return null;
    }

    /**
     * Flush cache for a specific file path
     *
     * @param  string $path S3 file path
     * @return bool True if cache was flushed, false if path doesn't match pattern
     */
    public function flushCacheForPath(string $path): bool
    {
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
     * @param  string $path S3 file path
     * @return string|null Cache key or null if path doesn't match pattern
     */
    public function getCacheKeyForPath(string $path): ?string
    {
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
     * @param  string $path S3 file path to load index for
     * @return array Index data containing file paths
     */
    public function loadIndex(string $path): array
    {
        $indexDetails = $this->extractIndexDetails($path);
        if ($indexDetails === null) {
            return [];
        }

        $blogId  = $indexDetails['blogId'] ?: '1';
        $year    = $indexDetails['year'];
        $month   = $indexDetails['month'];

        $cacheKey   = "index_{$blogId}_{$year}_{$month}";
        $cachedData = $this->cache->get($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }

        $file = $this->fileSystem->getCacheDir() . "/s3-index-{$blogId}-{$year}-{$month}.json";

        $this->logger->log("Loading index from file: {$file}");

        if (!$this->fileSystem->fileExists($file)) {
            return [];
        }

        $data   = $this->fileSystem->fileGetContents($file);
        $index  = json_decode($data, true) ?: [];

        $this->cache->set($cacheKey, $index, 3600);
        
        return $index;
    }

    /**
     * Get file statistics.
     * 
     * Implementation of PHP's url_stat for the stream wrapper.
     * Returns basic file stats if the file exists in the index.
     * 
     * @param  string $path  Path to stat
     * @param  int    $flags Stat flags
     * @return array|false File statistics or false if file doesn't exist
     */
    public function url_stat(string $path, int $flags) : array|false
    {
        $normalized = $this->normalize($path);
        $index      = $this->loadIndex($normalized);
        
        //No index found, return false to delegate to original wrapper
        if (empty($index)) {
            return false;
        }

        if (has_filter('image_downsize')) {
            return isset($index[$normalized]) ? [
                'size' => 1,
                'mtime' => time()
            ] : false;
        }

        return isset($index[$normalized]) ? [
            'size' => 1,
            'mtime' => time()
        ] : [
            'size' => 0,
            'mtime' => time()
        ];
    }

    /**
     * Normalize a path by removing protocol and leading slashes.
     * 
     * @param  string $path Path to normalize
     * @return string Normalized path
     */
    public function normalize(string $path): string
    {
        return ltrim(str_replace('s3://', '', $path), '/');
    }
}