<?php

namespace S3_Local_Index\Cache;

use WpService\Contracts\Cache;

/**
 * Cache factory for creating cache instances
 */
class CacheFactory {
    
    /**
     * Create a composite cache with StaticCache and WpCache
     *
     * @param Cache|null $wpService WordPress cache service
     * @return CacheInterface
     */
    public static function createDefault(?Cache $wpService = null): CacheInterface {
        $staticCache = new StaticCache();
        
        if ($wpService === null) {
            // Fallback to static cache only if no wp service is provided
            return $staticCache;
        }
        
        return new CompositeCache(
            $staticCache,
            new WpCache($wpService)
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
     * @param Cache $wpService WordPress cache service
     * @return CacheInterface
     */
    public static function createWp(Cache $wpService): CacheInterface {
        return new WpCache($wpService);
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