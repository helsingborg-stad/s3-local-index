<?php

namespace S3_Local_Index\Cache;

interface CacheInterface {
    
    /**
     * Get data from cache
     *
     * @param string $key Cache key
     * @return mixed|null Returns cached data or null if not found
     */
    public function get(string $key);

    /**
     * Set data in cache
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $ttl Time to live in seconds (optional)
     * @return bool True on success, false on failure
     */
    public function set(string $key, $data, int $ttl = 0): bool;

    /**
     * Check if key exists in cache
     *
     * @param string $key Cache key
     * @return bool True if key exists, false otherwise
     */
    public function has(string $key): bool;

    /**
     * Delete data from cache
     *
     * @param string $key Cache key
     * @return bool True on success, false on failure
     */
    public function delete(string $key): bool;

    /**
     * Clear all cache data
     *
     * @return bool True on success, false on failure
     */
    public function clear(): bool;
}