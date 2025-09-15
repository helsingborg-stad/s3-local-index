<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\Cache\CacheFactory;

class Reader {

    private static array $index = [];
    private static ?CacheInterface $cache = null;

    private string $key = '';
    private int $position = 0;

    /**
     * Set the cache instance to use
     *
     * @param CacheInterface $cache
     */
    public static function setCache(CacheInterface $cache): void {
        self::$cache = $cache;
    }

    /**
     * Get the current cache instance or create default
     *
     * @return CacheInterface
     */
    public static function getCache(): CacheInterface {
        if (self::$cache === null) {
            self::$cache = CacheFactory::createDefault();
        }
        return self::$cache;
    }

    public static function loadIndex(string $path): array {
        $path = ltrim($path, '/');
        if (!preg_match('#uploads(?:/networks/\d+/sites/(\d+))?/(\d{4})/(\d{2})/#', $path, $m)) {
            return [];
        }

        $blog_id = $m[1] ?? '1';
        $year    = $m[2];
        $month   = $m[3];

        // Create cache key
        $cache_key = "index_{$blog_id}_{$year}_{$month}";
        
        // Try to get from cache first
        $cache = self::getCache();
        $cached_data = $cache->get($cache_key);
        if ($cached_data !== null) {
            return $cached_data;
        }

        // Load from file if not in cache
        $file = sys_get_temp_dir() . "/s3-index-temp/s3-index-{$blog_id}-{$year}-{$month}.json";
        if (!file_exists($file)) {
            return [];
        }

        $data = file_get_contents($file);
        $index = json_decode($data, true) ?: [];
        
        // Store in cache for next time (cache for 1 hour)
        $cache->set($cache_key, $index, 3600);
        
        return $index;
    }

    public function stream_open(string $path, string $mode, int $options, &$opened_path): bool {
        self::$index = self::loadIndex($path);
        $normalized = $this->normalize($path);
        if (!isset(self::$index[$normalized])) {
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
        self::$index = self::loadIndex($path);
        $normalized = $this->normalize($path);
        return isset(self::$index[$normalized]) ? ['size' => 1, 'mtime' => time()] : false;
    }

    private function normalize(string $path): string {
        return ltrim(str_replace('s3://', '', $path), '/');
    }
}