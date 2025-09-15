<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\FileSystem\FileSystemInterface;

class Reader {

    private array $index = [];
    private string $key = '';
    private int $position = 0;

    /**
     * Constructor with dependency injection
     *
     * @param CacheInterface $cache
     * @param FileSystemInterface $fileSystem
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
        $file = $this->fileSystem->getTempDir() . "/s3-index-temp/s3-index-{$blogId}-{$year}-{$month}.json";
        if (!$this->fileSystem->fileExists($file)) {
            return [];
        }

        $data = $this->fileSystem->fileGetContents($file);
        $index = json_decode($data, true) ?: [];
        
        // Store in cache for next time (cache for 1 hour)
        $this->cache->set($cacheKey, $index, 3600);
        
        return $index;
    }

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

    public function stream_read(int $count): string {
        $data = file_get_contents('s3://' . $this->key);
        $chunk = substr($data, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_eof(): bool {
        $data = file_get_contents('s3://' . $this->key);
        return $this->position >= strlen($data);
    }

    public function url_stat(string $path, int $flags) {
        $this->index = $this->loadIndex($path);
        $normalized = $this->normalize($path);
        return isset($this->index[$normalized]) ? ['size' => 1, 'mtime' => time()] : false;
    }

    private function normalize(string $path): string {
        return ltrim(str_replace('s3://', '', $path), '/');
    }
}