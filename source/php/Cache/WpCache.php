<?php

namespace S3_Local_Index\Cache;

use WpService\Contracts\Cache;

/**
 * WordPress object cache implementation
 * Uses WordPress's wp_cache_* functions for persistent caching
 */
class WpCache implements CacheInterface {
    
    private string $group = 's3_local_index';

    /**
     * Constructor.
     *
     * @param Cache $wpService The WordPress cache service.
     */
    public function __construct(private Cache $wpService) {}

    /**
     * Get data from cache
     *
     * @param string $key Cache key
     * @return mixed|null Returns cached data or null if not found
     */
    public function get(string $key) {
        $data = $this->wpService->wpCacheGet($key, $this->group);
        return $data === false ? null : $data;
    }

    /**
     * Set data in cache
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $ttl Time to live in seconds (optional, 0 = default expiration)
     * @return bool True on success, false on failure
     */
    public function set(string $key, $data, int $ttl = 0): bool {
        // WordPress cache expiration: 0 means use default, empty string means no expiration
        $expiration = $ttl > 0 ? $ttl : 0;
        
        return $this->wpService->wpCacheSet($key, $data, $this->group, $expiration);
    }

    /**
     * Check if key exists in cache
     *
     * @param string $key Cache key
     * @return bool True if key exists, false otherwise
     */
    public function has(string $key): bool {
        return $this->get($key) !== null;
    }

    /**
     * Delete data from cache
     *
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool {
        return $this->wpService->wpCacheDelete($key, $this->group);
    }

    /**
     * Clear all cache data for this group
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool {
        // Try group-specific flush first
        $result = $this->wpService->wpCacheFlushGroup($this->group);
        
        // Fallback: wp_cache_flush clears entire cache
        if (!$result) {
            return $this->wpService->wpCacheFlush();
        }
        
        return $result;
    }
}