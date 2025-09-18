<?php

namespace S3_Local_Index\Cache;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use WpService\Implementations\FakeWpService;

class CacheFactoryTest extends TestCase
{
    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $cacheFactory = new CacheFactory($this->getWpService());

        $this->assertInstanceOf(CacheFactory::class, $cacheFactory);
    }

    #[TestDox('createDefault returns CompositeCache instance')]
    public function testCreateDefaultReturnsCompositeCacheInstance(): void
    {
        $cacheFactory = new CacheFactory($this->getWpService());

        $cache = $cacheFactory->createDefault();

        $this->assertInstanceOf(CompositeCache::class, $cache);
    }

    #[TestDox('createStatic returns StaticCache instance')]
    public function testCreateStaticReturnsStaticCacheInstance(): void
    {
        $cacheFactory = new CacheFactory($this->getWpService());

        $cache = $cacheFactory->createStatic();

        $this->assertInstanceOf(StaticCache::class, $cache);
    }

    #[TestDox('createWp returns WpCache instance')]
    public function testCreateWpReturnsWpCacheInstance(): void
    {
        $cacheFactory = new CacheFactory($this->getWpService());

        $cache = $cacheFactory->createWp();

        $this->assertInstanceOf(WpCache::class, $cache);
    }

    #[TestDox('createComposite returns CompositeCache instance')]
    public function testCreateCompositeReturnsCompositeCacheInstance(): void
    {
        $cacheFactory = new CacheFactory($this->getWpService());
        $staticCache = new StaticCache();
        $wpCache = new WpCache($this->getWpService());

        $cache = $cacheFactory->createComposite($staticCache, $wpCache);

        $this->assertInstanceOf(CompositeCache::class, $cache);
    }

    #[TestDox('createComposite with single cache works')]
    public function testCreateCompositeWithSingleCacheWorks(): void
    {
        $cacheFactory = new CacheFactory($this->getWpService());
        $staticCache = new StaticCache();

        $cache = $cacheFactory->createComposite($staticCache);

        $this->assertInstanceOf(CompositeCache::class, $cache);
    }

    #[TestDox('createComposite with multiple caches works')]
    public function testCreateCompositeWithMultipleCachesWorks(): void
    {
        $cacheFactory = new CacheFactory($this->getWpService());
        $staticCache1 = new StaticCache();
        $staticCache2 = new StaticCache();
        $wpCache = new WpCache($this->getWpService());

        $cache = $cacheFactory->createComposite($staticCache1, $staticCache2, $wpCache);

        $this->assertInstanceOf(CompositeCache::class, $cache);
    }

    private function getWpService(): FakeWpService
    {
        return new FakeWpService(
            [
            'wpCacheGet' => false,
            'wpCacheSet' => true,
            'wpCacheDelete' => true,
            'wpCacheFlush' => true
            ]
        );
    }
}
