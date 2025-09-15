<?php

namespace S3_Local_Index\Stream;

use PHPUnit\Framework\TestCase;
use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\FileSystem\FileSystemInterface;

class ReaderTest extends TestCase
{
    private string $testDir;
    private CacheInterface $cache;
    private FileSystemInterface $fileSystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/s3-reader-test-' . uniqid();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
        $this->cache = $this->createCache();
        $this->fileSystem = $this->createFileSystem();
    }

    protected function tearDown(): void
    {
        // Clean up test files
        foreach (glob($this->testDir . '/*') as $file) {
            unlink($file);
        }
        if (is_dir($this->testDir)) {
            rmdir($this->testDir);
        }
        parent::tearDown();
    }

    /**
     * @testdox class can be instantiated
     */
    public function testClassCanBeInstantiated(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem);

        $this->assertInstanceOf(Reader::class, $reader);
    }

    /**
     * @testdox extractIndexDetails works with multisite pattern
     */
    public function testExtractIndexDetailsWorksWithMultisitePattern(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem);
        
        $path = 'uploads/networks/1/sites/5/2023/01/image.jpg';
        $result = $reader->extractIndexDetails($path);

        $this->assertIsArray($result);
        $this->assertEquals('5', $result['blogId']);
        $this->assertEquals('2023', $result['year']);
        $this->assertEquals('01', $result['month']);
    }

    /**
     * @testdox extractIndexDetails works with single site pattern
     */
    public function testExtractIndexDetailsWorksWithSingleSitePattern(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem);
        
        $path = 'uploads/2023/01/image.jpg';
        $result = $reader->extractIndexDetails($path);

        $this->assertIsArray($result);
        $this->assertEquals('1', $result['blogId']);
        $this->assertEquals('2023', $result['year']);
        $this->assertEquals('01', $result['month']);
    }

    /**
     * @testdox extractIndexDetails handles leading slash
     */
    public function testExtractIndexDetailsHandlesLeadingSlash(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem);
        
        $path = '/uploads/2023/01/image.jpg';
        $result = $reader->extractIndexDetails($path);

        $this->assertIsArray($result);
        $this->assertEquals('1', $result['blogId']);
        $this->assertEquals('2023', $result['year']);
        $this->assertEquals('01', $result['month']);
    }

    /**
     * @testdox extractIndexDetails returns null for invalid pattern
     */
    public function testExtractIndexDetailsReturnsNullForInvalidPattern(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem);
        
        $path = 'invalid/path/structure.jpg';
        $result = $reader->extractIndexDetails($path);

        $this->assertNull($result);
    }

    /**
     * @testdox getCacheKeyForPath returns correct cache key
     */
    public function testGetCacheKeyForPathReturnsCorrectCacheKey(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem);
        
        $path = 'uploads/2023/01/image.jpg';
        $result = $reader->getCacheKeyForPath($path);

        $this->assertEquals('index_1_2023_01', $result);
    }

    /**
     * @testdox getCacheKeyForPath returns null for invalid pattern
     */
    public function testGetCacheKeyForPathReturnsNullForInvalidPattern(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem);
        
        $path = 'invalid/path/structure.jpg';
        $result = $reader->getCacheKeyForPath($path);

        $this->assertNull($result);
    }

    /**
     * @testdox flushCacheForPath deletes cache key
     */
    public function testFlushCacheForPathDeletesCacheKey(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem);
        
        $path = 'uploads/2023/01/image.jpg';
        $result = $reader->flushCacheForPath($path);

        $this->assertTrue($result);
    }

    /**
     * @testdox flushCacheForPath returns false for invalid pattern
     */
    public function testFlushCacheForPathReturnsFalseForInvalidPattern(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem);
        
        $path = 'invalid/path/structure.jpg';
        $result = $reader->flushCacheForPath($path);

        $this->assertFalse($result);
    }

    /**
     * @testdox loadIndex returns cached data when available
     */
    public function testLoadIndexReturnsCachedDataWhenAvailable(): void
    {
        $cachedData = ['uploads/2023/01/image1.jpg', 'uploads/2023/01/image2.jpg'];
        $cache = $this->createCache(['index_1_2023_01' => $cachedData]);
        $reader = new Reader($cache, $this->fileSystem);
        
        $path = 'uploads/2023/01/image.jpg';
        $result = $reader->loadIndex($path);

        $this->assertEquals($cachedData, $result);
    }

    /**
     * @testdox loadIndex loads from file when not cached
     */
    public function testLoadIndexLoadsFromFileWhenNotCached(): void
    {
        $indexData = ['uploads/2023/01/image1.jpg', 'uploads/2023/01/image2.jpg'];
        $indexFile = $this->testDir . '/s3-index-1-2023-01.json';
        file_put_contents($indexFile, json_encode($indexData));
        
        $reader = new Reader($this->cache, $this->fileSystem);
        
        $path = 'uploads/2023/01/image.jpg';
        $result = $reader->loadIndex($path);

        $this->assertEquals($indexData, $result);
    }

    /**
     * @testdox loadIndex returns empty array when file does not exist
     */
    public function testLoadIndexReturnsEmptyArrayWhenFileDoesNotExist(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem);
        
        $path = 'uploads/2023/01/image.jpg';
        $result = $reader->loadIndex($path);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * @testdox loadIndex returns empty array for invalid pattern
     */
    public function testLoadIndexReturnsEmptyArrayForInvalidPattern(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem);
        
        $path = 'invalid/path/structure.jpg';
        $result = $reader->loadIndex($path);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    private function createCache(array $data = []): CacheInterface
    {
        return new class($data) implements CacheInterface {
            public function __construct(private array $data) {}

            public function get(string $key)
            {
                return $this->data[$key] ?? null;
            }

            public function set(string $key, $data, int $ttl = 0): bool
            {
                $this->data[$key] = $data;
                return true;
            }

            public function has(string $key): bool
            {
                return isset($this->data[$key]);
            }

            public function delete(string $key): bool
            {
                unset($this->data[$key]);
                return true;
            }

            public function clear(): bool
            {
                $this->data = [];
                return true;
            }
        };
    }

    private function createFileSystem(): FileSystemInterface
    {
        return new class($this->testDir) implements FileSystemInterface {
            public function __construct(private string $cacheDir) {}

            public function fileExists(string $path): bool
            {
                return file_exists($path);
            }

            public function fileGetContents(string $path)
            {
                return file_get_contents($path);
            }

            public function filePutContents(string $path, string $data)
            {
                return file_put_contents($path, $data);
            }

            public function unlink(string $path): bool
            {
                return unlink($path);
            }

            public function getTempDir(): string
            {
                return sys_get_temp_dir();
            }

            public function getCacheDir(): string
            {
                return $this->cacheDir;
            }
        };
    }
}