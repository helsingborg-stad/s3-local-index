<?php

namespace S3_Local_Index\Cache;

/**
 * Composite cache implementation that uses multiple cache layers
 * Tries caches in order, stores in all caches on set
 */
class CompositeCache implements CacheInterface
{
    use CacheIdentifierTrait;
    
    private array $caches = [];

    /**
     * Constructor
     *
     * @param CacheInterface ...$caches Multiple cache implementations
     */
    public function __construct(CacheInterface ...$caches)
    {
        $this->caches = $caches;
    }

    /**
     * Get data from cache - tries each cache in order until found
     *
     * @param  string $key Cache key
     * @return mixed|null Returns cached data or null if not found
     */
    public function get(string $key)
    {
        foreach ($this->caches as $cache) {
            $data = $cache->get($key);
            if ($data !== null) {
                // Found in this cache, populate any earlier caches that didn't have it
                $this->populateEarlierCaches($key, $data);
                return $data;
            }
        }

        return null;
    }

    /**
     * Set data in all caches
     *
     * @param  string $key  Cache key
     * @param  mixed  $data Data to cache
     * @param  int    $ttl  Time to live in seconds (optional)
     * @return bool True if at least one cache succeeded, false if all failed
     */
    public function set(string $key, $data, int $ttl = 0): bool
    {
        $success = false;
        
        foreach ($this->caches as $cache) {
            if ($cache->set($key, $data, $ttl)) {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * Check if key exists in any cache
     *
     * @param  string $key Cache key
     * @return bool True if key exists in any cache, false otherwise
     */
    public function has(string $key): bool
    {
        foreach ($this->caches as $cache) {
            if ($cache->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Delete data from all caches
     *
     * @param  string $key Cache key
     * @return bool True if at least one cache succeeded, false if all failed
     */
    public function delete(string $key): bool
    {
        $success = false;
        
        foreach ($this->caches as $cache) {
            if ($cache->delete($key)) {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * Clear all caches
     *
     * @return bool True if at least one cache succeeded, false if all failed
     */
    public function clear(): bool
    {
        $success = false;
        
        foreach ($this->caches as $cache) {
            if ($cache->clear()) {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * Populate earlier caches with data found in later cache
     *
     * @param string $key  Cache key
     * @param mixed  $data Data to populate
     */
    private function populateEarlierCaches(string $key, $data): void
    {
        foreach ($this->caches as $cache) {
            if (!$cache->has($key)) {
                $cache->set($key, $data);
            } else {
                // This cache already has the data, stop here
                break;
            }
        }
    }
}