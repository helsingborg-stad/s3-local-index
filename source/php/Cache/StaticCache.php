<?php

namespace S3_Local_Index\Cache;

/**
 * Static in-memory cache implementation
 * Stores data in static arrays for the duration of the request
 */
class StaticCache implements CacheInterface {
    
    private static array $cache = [];
    private static array $ttl_cache = [];

    /**
     * Get data from cache
     *
     * @param string $key Cache key
     * @return mixed|null Returns cached data or null if not found
     */
    public function get(string $key) {
        // Check if key exists and is not expired
        if (!$this->has($key)) {
            return null;
        }

        return self::$cache[$key];
    }

    /**
     * Set data in cache
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $ttl Time to live in seconds (optional, 0 = no expiration)
     * @return bool True on success, false on failure
     */
    public function set(string $key, $data, int $ttl = 0): bool {
        self::$cache[$key] = $data;
        
        if ($ttl > 0) {
            self::$ttl_cache[$key] = time() + $ttl;
        } else {
            // No expiration
            unset(self::$ttl_cache[$key]);
        }

        return true;
    }

    /**
     * Check if key exists in cache and is not expired
     *
     * @param string $key Cache key
     * @return bool True if key exists and is valid, false otherwise
     */
    public function has(string $key): bool {
        // Check if key exists
        if (!isset(self::$cache[$key])) {
            return false;
        }

        // Check expiration if TTL is set
        if (isset(self::$ttl_cache[$key])) {
            if (time() > self::$ttl_cache[$key]) {
                // Expired, remove from cache
                unset(self::$cache[$key]);
                unset(self::$ttl_cache[$key]);
                return false;
            }
        }

        return true;
    }

    /**
     * Delete data from cache
     *
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool {
        unset(self::$cache[$key]);
        unset(self::$ttl_cache[$key]);
        return true;
    }

    /**
     * Clear all cache data
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool {
        self::$cache = [];
        self::$ttl_cache = [];
        return true;
    }
}