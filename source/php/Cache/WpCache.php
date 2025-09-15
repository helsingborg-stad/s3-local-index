<?php

namespace S3_Local_Index\Cache;

/**
 * WordPress object cache implementation
 * Uses WordPress's wp_cache_* functions for persistent caching
 */
class WpCache implements CacheInterface {
    
    private string $group = 's3_local_index';

    /**
     * Get data from cache
     *
     * @param string $key Cache key
     * @return mixed|null Returns cached data or null if not found
     */
    public function get(string $key) {
        if (!function_exists('wp_cache_get')) {
            return null;
        }

        $data = wp_cache_get($key, $this->group);
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
        if (!function_exists('wp_cache_set')) {
            return false;
        }

        // WordPress cache expiration: 0 means use default, empty string means no expiration
        $expiration = $ttl > 0 ? $ttl : 0;
        
        return wp_cache_set($key, $data, $this->group, $expiration);
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
        if (!function_exists('wp_cache_delete')) {
            return false;
        }

        return wp_cache_delete($key, $this->group);
    }

    /**
     * Clear all cache data for this group
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool {
        if (!function_exists('wp_cache_flush_group')) {
            // Fallback: wp_cache_flush clears entire cache
            if (function_exists('wp_cache_flush')) {
                return wp_cache_flush();
            }
            return false;
        }

        return wp_cache_flush_group($this->group);
    }
}