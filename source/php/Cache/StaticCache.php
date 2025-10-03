<?php

namespace S3_Local_Index\Cache;

/**
 * Simple static in-memory cache implementation.
 * 
 * Stores data in static arrays for the duration of the request.
 * This is a basic cache without LRU eviction or capacity limits.
 */
class StaticCache implements CacheInterface
{
    use CacheIdentifierTrait;

    private static array $cache = [];     // key => value
    private static array $ttlCache = [];  // key => expiry timestamp

    public function get(string $key)
    {
        if (!$this->has($key)) {
            return null;
        }
        return self::$cache[$key];
    }

    public function set(string $key, $data, int $ttl = 0): bool
    {
        self::$cache[$key] = $data;

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
        unset(self::$cache[$key], self::$ttlCache[$key]);
        return true;
    }

    public function clear(): bool
    {
        self::$cache = [];
        self::$ttlCache = [];
        return true;
    }
}