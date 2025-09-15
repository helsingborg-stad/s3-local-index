<?php

namespace S3_Local_Index\Cache;

use WpService\WpService;

/**
 * Cache factory for creating cache instances using dependency injection
 */
class CacheFactory {
    
    /**
     * Constructor
     *
     * @param WpService $wpService WordPress cache service
     */
    public function __construct(private WpService $wpService) {}
    
    /**
     * Create a composite cache with StaticCache and WpCache
     *
     * @return CacheInterface
     */
    public function createDefault(): CacheInterface {
        $staticCache = new StaticCache();
        
        return new CompositeCache(
            $staticCache,
            new WpCache($this->wpService)
        );
    }

    /**
     * Create a StaticCache instance
     *
     * @return CacheInterface
     */
    public function createStatic(): CacheInterface {
        return new StaticCache();
    }

    /**
     * Create a WpCache instance
     *
     * @return CacheInterface
     */
    public function createWp(): CacheInterface {
        return new WpCache($this->wpService);
    }

    /**
     * Create a CompositeCache with custom cache instances
     *
     * @param CacheInterface ...$caches
     * @return CacheInterface
     */
    public function createComposite(CacheInterface ...$caches): CacheInterface {
        return new CompositeCache(...$caches);
    }
}