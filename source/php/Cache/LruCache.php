<?php

namespace S3_Local_Index\Cache;

/**
 * In-memory LRU (Least Recently Used) cache implementation.
 * 
 * Keeps a limited number of items in memory for the duration of the request.
 * When capacity is reached, the least recently accessed item is removed.
 */
class LruCache implements CacheInterface
{
    private static array $cache = [];     // key => value
    private static array $ttlCache = [];  // key => expiry timestamp
    private static array $usage = [];     // key => last access time

    private static int $capacity = 1000;   // default max items

    public function __construct(int $capacity = 1000)
    {
        self::$capacity = $capacity;
    }

    public function get(string $key)
    {
        if (!$this->has($key)) {
            return null;
        }
        self::$usage[$key] = microtime(true);
        return self::$cache[$key];
    }

    public function set(string $key, $data, int $ttl = 0): bool
    {
        // If capacity is full, evict least recently used
        if (count(self::$cache) >= self::$capacity && !isset(self::$cache[$key])) {
            $this->evict();
        }

        self::$cache[$key] = $data;
        self::$usage[$key] = microtime(true);

        if ($ttl > 0) {
            self::$ttlCache[$key] = time() + $ttl;
        } else {
            unset(self::$ttlCache[$key]);
        }

        return true;
    }

    public function has(string $key): bool
    {
        if (!isset(self::$cache[$key])) {
            return false;
        }

        if (isset(self::$ttlCache[$key]) && time() > self::$ttlCache[$key]) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset(self::$cache[$key], self::$ttlCache[$key], self::$usage[$key]);
        return true;
    }

    public function clear(): bool
    {
        self::$cache = [];
        self::$ttlCache = [];
        self::$usage = [];
        return true;
    }

    /**
     * Evict the least recently used item.
     */
    private function evict(): void
    {
        if (empty(self::$usage)) {
            return;
        }

        $oldestKey = array_key_first(self::$usage);
        $oldestTime = self::$usage[$oldestKey];

        foreach (self::$usage as $key => $time) {
            if ($time < $oldestTime) {
                $oldestKey = $key;
                $oldestTime = $time;
            }
        }

        $this->delete($oldestKey);
    }
}