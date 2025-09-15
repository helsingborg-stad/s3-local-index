<?php

namespace S3_Local_Index\Cache;

/**
 * Cache factory for creating cache instances
 */
class CacheFactory {
    
    /**
     * Create a composite cache with StaticCache and WpCache
     *
     * @return CacheInterface
     */
    public static function createDefault(): CacheInterface {
        return new CompositeCache(
            new StaticCache(),
            new WpCache()
        );
    }

    /**
     * Create a StaticCache instance
     *
     * @return CacheInterface
     */
    public static function createStatic(): CacheInterface {
        return new StaticCache();
    }

    /**
     * Create a WpCache instance
     *
     * @return CacheInterface
     */
    public static function createWp(): CacheInterface {
        return new WpCache();
    }

    /**
     * Create a CompositeCache with custom cache instances
     *
     * @param CacheInterface ...$caches
     * @return CacheInterface
     */
    public static function createComposite(CacheInterface ...$caches): CacheInterface {
        return new CompositeCache(...$caches);
    }
}