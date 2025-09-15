<?php

namespace S3_Local_Index\Cache;

use PHPUnit\Framework\TestCase;
use WpService\Implementations\FakeWpService;

class WpCacheTest extends TestCase
{
    /**
     * @testdox class can be instantiated
     */
    public function testClassCanBeInstantiated(): void
    {
        $wpCache = new WpCache($this->getWpService());

        $this->assertInstanceOf(WpCache::class, $wpCache);
    }

    /**
     * @testdox set stores data using wp_cache_set
     */
    public function testSetStoresDataUsingWpCacheSet(): void
    {
        $wpService = $this->getWpService();
        $wpCache = new WpCache($wpService);
        
        $key = 'test_key';
        $value = 'test_value';

        $result = $wpCache->set($key, $value);

        $this->assertTrue($result);
    }

    /**
     * @testdox get retrieves data using wp_cache_get
     */
    public function testGetRetrievesDataUsingWpCacheGet(): void
    {
        $value = 'test_value';
        $wpService = $this->getWpService(['wpCacheGet' => $value]);
        $wpCache = new WpCache($wpService);
        
        $result = $wpCache->get('test_key');

        $this->assertEquals($value, $result);
    }

    /**
     * @testdox get returns null when wp_cache_get returns false
     */
    public function testGetReturnsNullWhenWpCacheGetReturnsFalse(): void
    {
        $wpService = $this->getWpService(['wpCacheGet' => false]);
        $wpCache = new WpCache($wpService);
        
        $result = $wpCache->get('test_key');

        $this->assertNull($result);
    }

    /**
     * @testdox has returns true when data exists
     */
    public function testHasReturnsTrueWhenDataExists(): void
    {
        $wpService = $this->getWpService(['wpCacheGet' => 'test_value']);
        $wpCache = new WpCache($wpService);
        
        $result = $wpCache->has('test_key');

        $this->assertTrue($result);
    }

    /**
     * @testdox has returns false when data does not exist
     */
    public function testHasReturnsFalseWhenDataDoesNotExist(): void
    {
        $wpService = $this->getWpService(['wpCacheGet' => false]);
        $wpCache = new WpCache($wpService);
        
        $result = $wpCache->has('test_key');

        $this->assertFalse($result);
    }

    /**
     * @testdox delete removes data using wp_cache_delete
     */
    public function testDeleteRemovesDataUsingWpCacheDelete(): void
    {
        $wpService = $this->getWpService();
        $wpCache = new WpCache($wpService);
        
        $result = $wpCache->delete('test_key');

        $this->assertTrue($result);
    }

    /**
     * @testdox clear uses wp_cache_flush_group first
     */
    public function testClearUsesWpCacheFlushGroupFirst(): void
    {
        $wpService = $this->getWpService(['wpCacheFlushGroup' => true]);
        $wpCache = new WpCache($wpService);
        
        $result = $wpCache->clear();

        $this->assertTrue($result);
    }

    /**
     * @testdox clear falls back to wp_cache_flush when flush_group fails
     */
    public function testClearFallsBackToWpCacheFlushWhenFlushGroupFails(): void
    {
        $wpService = $this->getWpService([
            'wpCacheFlushGroup' => false,
            'wpCacheFlush' => true
        ]);
        $wpCache = new WpCache($wpService);
        
        $result = $wpCache->clear();

        $this->assertTrue($result);
    }

    /**
     * @testdox set with TTL passes correct parameters
     */
    public function testSetWithTtlPassesCorrectParameters(): void
    {
        $wpService = $this->getWpService();
        $wpCache = new WpCache($wpService);
        
        $result = $wpCache->set('test_key', 'test_value', 3600);

        $this->assertTrue($result);
    }

    /**
     * @testdox set with zero TTL passes zero as expiration
     */
    public function testSetWithZeroTtlPassesZeroAsExpiration(): void
    {
        $wpService = $this->getWpService();
        $wpCache = new WpCache($wpService);
        
        $result = $wpCache->set('test_key', 'test_value', 0);

        $this->assertTrue($result);
    }

    private function getWpService(array $overrides = []): FakeWpService
    {
        $defaults = [
            'wpCacheGet' => false,
            'wpCacheSet' => true,
            'wpCacheDelete' => true,
            'wpCacheFlush' => true,
            'wpCacheFlushGroup' => true
        ];

        return new FakeWpService(array_merge($defaults, $overrides));
    }
}