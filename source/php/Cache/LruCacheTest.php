<?php

namespace S3_Local_Index\Cache;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class StaticCacheTest extends TestCase
{
    private StaticCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = new StaticCache();
        // Clear cache before each test
        $this->cache->clear();
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        $this->cache->clear();
        parent::tearDown();
    }

    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $cache = new StaticCache();

        $this->assertInstanceOf(StaticCache::class, $cache);
    }

    #[TestDox('set and get work correctly')]
    public function testSetAndGetWorkCorrectly(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $setResult = $this->cache->set($key, $value);
        $getValue = $this->cache->get($key);

        $this->assertTrue($setResult);
        $this->assertEquals($value, $getValue);
    }

    #[TestDox('get returns null for non-existent key')]
    public function testGetReturnsNullForNonExistentKey(): void
    {
        $result = $this->cache->get('non_existent_key');

        $this->assertNull($result);
    }

    #[TestDox('has returns true for existing key')]
    public function testHasReturnsTrueForExistingKey(): void
    {
        $key = 'test_key';
        $this->cache->set($key, 'test_value');

        $result = $this->cache->has($key);

        $this->assertTrue($result);
    }

    #[TestDox('has returns false for non-existent key')]
    public function testHasReturnsFalseForNonExistentKey(): void
    {
        $result = $this->cache->has('non_existent_key');

        $this->assertFalse($result);
    }

    #[TestDox('delete removes existing key')]
    public function testDeleteRemovesExistingKey(): void
    {
        $key = 'test_key';
        $this->cache->set($key, 'test_value');

        $deleteResult = $this->cache->delete($key);
        $hasResult = $this->cache->has($key);

        $this->assertTrue($deleteResult);
        $this->assertFalse($hasResult);
    }

    #[TestDox('delete returns true for non-existent key')]
    public function testDeleteReturnsTrueForNonExistentKey(): void
    {
        $result = $this->cache->delete('non_existent_key');

        $this->assertTrue($result);
    }

    #[TestDox('clear removes all keys')]
    public function testClearRemovesAllKeys(): void
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $clearResult = $this->cache->clear();
        $has1 = $this->cache->has('key1');
        $has2 = $this->cache->has('key2');

        $this->assertTrue($clearResult);
        $this->assertFalse($has1);
        $this->assertFalse($has2);
    }

    #[TestDox('TTL expiration works correctly')]
    public function testTtlExpirationWorksCorrectly(): void
    {
        $key = 'test_key';
        $value = 'test_value';
        $ttl = 1; // 1 second

        $this->cache->set($key, $value, $ttl);
        
        // Should exist immediately
        $this->assertTrue($this->cache->has($key));
        $this->assertEquals($value, $this->cache->get($key));

        // Sleep to let TTL expire
        sleep(2);

        // Should be expired now
        $this->assertFalse($this->cache->has($key));
        $this->assertNull($this->cache->get($key));
    }

    #[TestDox('set with zero TTL means no expiration')]
    public function testSetWithZeroTtlMeansNoExpiration(): void
    {
        $key = 'test_key';
        $value = 'test_value';

        $this->cache->set($key, $value, 0);
        
        // Should exist
        $this->assertTrue($this->cache->has($key));
        $this->assertEquals($value, $this->cache->get($key));

        // Sleep a bit
        sleep(1);

        // Should still exist (no expiration)
        $this->assertTrue($this->cache->has($key));
        $this->assertEquals($value, $this->cache->get($key));
    }

    #[TestDox('can store different data types')]
    public function testCanStoreDifferentDataTypes(): void
    {
        $testCases = [
            'string' => 'test string',
            'integer' => 42,
            'float' => 3.14,
            'boolean_true' => true,
            'boolean_false' => false,
            'array' => ['a', 'b', 'c'],
            'object' => (object)['property' => 'value']
        ];

        foreach ($testCases as $key => $value) {
            $this->cache->set($key, $value);
            $this->assertEquals($value, $this->cache->get($key));
        }
    }
}
