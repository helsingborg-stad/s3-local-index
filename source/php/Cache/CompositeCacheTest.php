<?php

namespace S3_Local_Index\Cache;

use PHPUnit\Framework\TestCase;

class CompositeCacheTest extends TestCase
{
    /**
     * @testdox class can be instantiated with single cache
     */
    public function testClassCanBeInstantiatedWithSingleCache(): void
    {
        $staticCache = new StaticCache();
        $compositeCache = new CompositeCache($staticCache);

        $this->assertInstanceOf(CompositeCache::class, $compositeCache);
    }

    /**
     * @testdox class can be instantiated with multiple caches
     */
    public function testClassCanBeInstantiatedWithMultipleCaches(): void
    {
        $staticCache1 = new StaticCache();
        $staticCache2 = new StaticCache();
        $compositeCache = new CompositeCache($staticCache1, $staticCache2);

        $this->assertInstanceOf(CompositeCache::class, $compositeCache);
    }

    /**
     * @testdox set stores data in all caches
     */
    public function testSetStoresDataInAllCaches(): void
    {
        $staticCache1 = new StaticCache();
        $staticCache2 = new StaticCache();
        $compositeCache = new CompositeCache($staticCache1, $staticCache2);
        
        $key = 'test_key';
        $value = 'test_value';

        $result = $compositeCache->set($key, $value);

        $this->assertTrue($result);
        $this->assertTrue($staticCache1->has($key));
        $this->assertTrue($staticCache2->has($key));
        $this->assertEquals($value, $staticCache1->get($key));
        $this->assertEquals($value, $staticCache2->get($key));
    }

    /**
     * @testdox get retrieves data from first available cache
     */
    public function testGetRetrievesDataFromFirstAvailableCache(): void
    {
        $staticCache1 = new StaticCache();
        $staticCache2 = new StaticCache();
        $compositeCache = new CompositeCache($staticCache1, $staticCache2);
        
        $key = 'test_key';
        $value = 'test_value';

        // Set only in second cache
        $staticCache2->set($key, $value);

        $result = $compositeCache->get($key);

        $this->assertEquals($value, $result);
        // Should now be populated in first cache too
        $this->assertTrue($staticCache1->has($key));
    }

    /**
     * @testdox get returns null when key not found in any cache
     */
    public function testGetReturnsNullWhenKeyNotFoundInAnyCache(): void
    {
        $staticCache1 = new StaticCache();
        $staticCache2 = new StaticCache();
        $compositeCache = new CompositeCache($staticCache1, $staticCache2);

        $result = $compositeCache->get('non_existent_key');

        $this->assertNull($result);
    }

    /**
     * @testdox has returns true if key exists in any cache
     */
    public function testHasReturnsTrueIfKeyExistsInAnyCache(): void
    {
        $staticCache1 = new StaticCache();
        $staticCache2 = new StaticCache();
        $compositeCache = new CompositeCache($staticCache1, $staticCache2);
        
        $key = 'test_key';
        $value = 'test_value';

        // Set only in second cache
        $staticCache2->set($key, $value);

        $result = $compositeCache->has($key);

        $this->assertTrue($result);
    }

    /**
     * @testdox has returns false if key does not exist in any cache
     */
    public function testHasReturnsFalseIfKeyDoesNotExistInAnyCache(): void
    {
        $staticCache1 = new StaticCache();
        $staticCache2 = new StaticCache();
        $compositeCache = new CompositeCache($staticCache1, $staticCache2);

        $result = $compositeCache->has('non_existent_key');

        $this->assertFalse($result);
    }

    /**
     * @testdox delete removes data from all caches
     */
    public function testDeleteRemovesDataFromAllCaches(): void
    {
        $staticCache1 = new StaticCache();
        $staticCache2 = new StaticCache();
        $compositeCache = new CompositeCache($staticCache1, $staticCache2);
        
        $key = 'test_key';
        $value = 'test_value';

        // Set in both caches
        $staticCache1->set($key, $value);
        $staticCache2->set($key, $value);

        $result = $compositeCache->delete($key);

        $this->assertTrue($result);
        $this->assertFalse($staticCache1->has($key));
        $this->assertFalse($staticCache2->has($key));
    }

    /**
     * @testdox clear removes all data from all caches
     */
    public function testClearRemovesAllDataFromAllCaches(): void
    {
        $staticCache1 = new StaticCache();
        $staticCache2 = new StaticCache();
        $compositeCache = new CompositeCache($staticCache1, $staticCache2);
        
        // Set data in both caches
        $staticCache1->set('key1', 'value1');
        $staticCache2->set('key2', 'value2');

        $result = $compositeCache->clear();

        $this->assertTrue($result);
        $this->assertFalse($staticCache1->has('key1'));
        $this->assertFalse($staticCache2->has('key2'));
    }

    /**
     * @testdox set returns true when at least one cache succeeds
     */
    public function testSetReturnsTrueWhenAtLeastOneCacheSucceeds(): void
    {
        $failingCache = $this->createFailingCache();
        $staticCache = new StaticCache();
        $compositeCache = new CompositeCache($failingCache, $staticCache);
        
        $result = $compositeCache->set('test_key', 'test_value');

        $this->assertTrue($result);
    }

    /**
     * @testdox delete returns true when at least one cache succeeds
     */
    public function testDeleteReturnsTrueWhenAtLeastOneCacheSucceeds(): void
    {
        $failingCache = $this->createFailingCache();
        $staticCache = new StaticCache();
        $compositeCache = new CompositeCache($failingCache, $staticCache);
        
        $staticCache->set('test_key', 'test_value');
        $result = $compositeCache->delete('test_key');

        $this->assertTrue($result);
    }

    /**
     * @testdox clear returns true when at least one cache succeeds
     */
    public function testClearReturnsTrueWhenAtLeastOneCacheSucceeds(): void
    {
        $failingCache = $this->createFailingCache();
        $staticCache = new StaticCache();
        $compositeCache = new CompositeCache($failingCache, $staticCache);
        
        $result = $compositeCache->clear();

        $this->assertTrue($result);
    }

    private function createFailingCache(): CacheInterface
    {
        return new class implements CacheInterface {
            public function get(string $key)
            {
                return null; 
            }
            public function set(string $key, $data, int $ttl = 0): bool
            {
                return false; 
            }
            public function has(string $key): bool
            {
                return false; 
            }
            public function delete(string $key): bool
            {
                return false; 
            }
            public function clear(): bool
            {
                return false; 
            }
        };
    }
}