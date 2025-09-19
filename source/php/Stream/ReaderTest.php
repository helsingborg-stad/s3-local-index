<?php

namespace S3_Local_Index\Stream;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3LocalIndex\Parser\Parser;
use S3_Local_Index\Logger\Logger;

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

    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());

        $this->assertInstanceOf(Reader::class, $reader);
    }

    #[TestDox('getCacheKeyForPath returns correct cache key')]
    public function testGetCacheKeyForPathReturnsCorrectCacheKey(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());
        
        $path = 'uploads/2023/01/image.jpg';
        $result = $reader->getCacheKeyForPath($path);

        $this->assertEquals('index_1_2023_01', $result);
    }

    #[TestDox('getCacheKeyForPath returns null for invalid pattern')]
    public function testGetCacheKeyForPathReturnsNullForInvalidPattern(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());
        
        $path = 'invalid/path/structure.jpg';
        $result = $reader->getCacheKeyForPath($path);

        $this->assertNull($result);
    }

    #[TestDox('flushCacheForPath deletes cache key')]
    public function testFlushCacheForPathDeletesCacheKey(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());
        
        $path = 'uploads/2023/01/image.jpg';
        $result = $reader->flushCacheForPath($path);

        $this->assertTrue($result);
    }

    #[TestDox('flushCacheForPath returns false for invalid pattern')]
    public function testFlushCacheForPathReturnsFalseForInvalidPattern(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());
        
        $path = 'invalid/path/structure.jpg';
        $result = $reader->flushCacheForPath($path);

        $this->assertFalse($result);
    }

    #[TestDox('loadIndex returns cached data when available')]
    public function testLoadIndexReturnsCachedDataWhenAvailable(): void
    {
        $cachedData = ['uploads/2023/01/image1.jpg', 'uploads/2023/01/image2.jpg'];
        $cache = $this->createCache(['index_1_2023_01' => $cachedData]);
        $reader = new Reader($cache, $this->createFileSystem(), new Logger(), new Parser());
        
        $path = 'uploads/2023/01/image.jpg';
        $result = $reader->loadIndex($path);

        $this->assertEquals($cachedData, $result);
    }

    #[TestDox('loadIndex loads from file when not cached')]
    public function testLoadIndexLoadsFromFileWhenNotCached(): void
    {
        $indexData = ['uploads/2023/01/image1.jpg', 'uploads/2023/01/image2.jpg'];
        $indexFile = $this->fileSystem->getCacheDir() . '/s3-index-1-2023-01.json';

        $indexFile = $file = $this->fileSystem->getCacheDir() . "/" . $this->fileSystem->getCacheFileName(
            ['blogId' => 1, 'year' => 2023, 'month' => '01']
        );
        
        file_put_contents($indexFile, json_encode($indexData));
        
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());

        $path = 'uploads/2023/01/image.jpg';
        $result = $reader->loadIndex($path);

        $this->assertEquals($indexData, $result);
    }

    #[TestDox('loadIndex returns empty array when file does not exist')]
    public function testLoadIndexReturnsEmptyArrayWhenFileDoesNotExist(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());
        
        $path = 'uploads/2023/01/image.jpg';
        $result = $reader->loadIndex($path);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[TestDox('loadIndex returns empty array for invalid pattern')]
    public function testLoadIndexReturnsEmptyArrayForInvalidPattern(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());
        
        $path = 'invalid/path/structure.jpg';
        $result = $reader->loadIndex($path);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[TestDox('updateIndex adds file to index')]
    public function testUpdateIndexAddsFileToIndex(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());
        
        $path = 'uploads/2023/01/image.jpg';
        $result = $reader->updateIndex($path);

        $this->assertTrue($result);
        
        // Verify the index was updated
        $index = $reader->loadIndex($path);
        $normalized = $reader->normalize($path);
        $this->assertTrue(isset($index[$normalized]));
    }

    #[TestDox('updateIndex returns false for invalid path pattern')]
    public function testUpdateIndexReturnsFalseForInvalidPathPattern(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());
        
        $path = 'invalid/path/structure.jpg';
        $result = $reader->updateIndex($path);

        $this->assertFalse($result);
    }

    #[TestDox('updateIndex preserves existing entries in index')]
    public function testUpdateIndexPreservesExistingEntriesInIndex(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());
        
        // Add first file
        $path1 = 'uploads/2023/01/image1.jpg';
        $reader->updateIndex($path1);
        
        // Add second file
        $path2 = 'uploads/2023/01/image2.jpg';
        $reader->updateIndex($path2);

        // Verify both files exist in index
        $index = $reader->loadIndex($path1);
        $normalized1 = $reader->normalize($path1);
        $normalized2 = $reader->normalize($path2);
        
        $this->assertTrue(isset($index[$normalized1]));
        $this->assertTrue(isset($index[$normalized2]));
    }

    #[TestDox('removeFromIndex removes file from index')]
    public function testRemoveFromIndexRemovesFileFromIndex(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());
        
        $path = 'uploads/2023/01/image.jpg';
        
        // First add the file
        $reader->updateIndex($path);
        
        // Then remove it
        $result = $reader->removeFromIndex($path);

        $this->assertTrue($result);
        
        // Verify the file was removed from index
        $index = $reader->loadIndex($path);
        $normalized = $reader->normalize($path);
        $this->assertFalse(isset($index[$normalized]));
    }

    #[TestDox('removeFromIndex returns false for invalid path pattern')]
    public function testRemoveFromIndexReturnsFalseForInvalidPathPattern(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());
        
        $path = 'invalid/path/structure.jpg';
        $result = $reader->removeFromIndex($path);

        $this->assertFalse($result);
    }

    #[TestDox('removeFromIndex preserves other entries in index')]
    public function testRemoveFromIndexPreservesOtherEntriesInIndex(): void
    {
        $reader = new Reader($this->cache, $this->fileSystem, new Logger(), new Parser());
        
        // Add two files
        $path1 = 'uploads/2023/01/image1.jpg';
        $path2 = 'uploads/2023/01/image2.jpg';
        $reader->updateIndex($path1);
        $reader->updateIndex($path2);
        
        // Remove one file
        $reader->removeFromIndex($path1);

        // Verify only the second file remains
        $index = $reader->loadIndex($path2);
        $normalized1 = $reader->normalize($path1);
        $normalized2 = $reader->normalize($path2);
        
        $this->assertFalse(isset($index[$normalized1]));
        $this->assertTrue(isset($index[$normalized2]));
    }

    private function createCache(array $data = []): CacheInterface
    {
        return new class($data) implements CacheInterface {
            public function __construct(private array $data)
            {
            }

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
                return array_key_exists($key, $this->data);
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
            public function __construct(private string $cacheDir)
            {
            }

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

            public function getCacheFileName(array $details): string
            {
                return "{$details['blogId']}-{$details['year']}-{$details['month']}.json";
            }
        };
    }
}
