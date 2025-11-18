<?php

namespace S3_Local_Index\Index;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use S3_Local_Index\Cache\StaticCache;
use S3_Local_Index\FileSystem\NativeFileSystem;
use S3_Local_Index\Logger\Logger;
use S3_Local_Index\Parser\PathParser;

class IndexManagerTest extends TestCase
{
    private IndexManager $indexManager;
    private StaticCache $cache;
    private NativeFileSystem $fileSystem;
    private Logger $logger;
    private PathParser $pathParser;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->cache = new StaticCache();
        $this->logger = new Logger($this->createMockConfig());
        $this->pathParser = new PathParser();
        $this->tempDir = sys_get_temp_dir() . '/s3-index-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
        $this->fileSystem = new NativeFileSystem($this->createMockConfigWithCacheDir($this->tempDir));
        
        $this->indexManager = new IndexManager(
            $this->cache,
            $this->fileSystem,
            $this->logger,
            $this->pathParser
        );
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    /**
     * Create a mock Config for testing.
     */
    private function createMockConfig(): \S3_Local_Index\Config\ConfigInterface
    {
        return new class implements \S3_Local_Index\Config\ConfigInterface {
            public function isEnabled(): bool
            {
                return true;
            }

            public function getCliPriority(): int
            {
                return 10;
            }

            public function getPluginPriority(): int
            {
                return 20;
            }

            public function getCacheDirectory(): string
            {
                return sys_get_temp_dir() . '/test-cache';
            }

            public function isDebugEnabled(): bool
            {
                return false;
            }
        };
    }

    /**
     * Create a mock Config with specific cache directory for testing.
     */
    private function createMockConfigWithCacheDir(string $cacheDir): \S3_Local_Index\Config\ConfigInterface
    {
        return new class($cacheDir) implements \S3_Local_Index\Config\ConfigInterface {
            public function __construct(private string $cacheDir)
            {
            }
            
            public function isEnabled(): bool
            {
                return true;
            }

            public function getCliPriority(): int
            {
                return 10;
            }

            public function getPluginPriority(): int
            {
                return 20;
            }

            public function getCacheDirectory(): string
            {
                return $this->cacheDir;
            }

            public function isDebugEnabled(): bool
            {
                return false;
            }
        };
    }

    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $this->assertInstanceOf(IndexManager::class, $this->indexManager);
        $this->assertInstanceOf(IndexManagerInterface::class, $this->indexManager);
    }

    #[TestDox('write stores file with metadata including size')]
    public function testWriteStoresFileWithMetadata(): void
    {
        $path = 'uploads/2024/01/test-image.jpg';
        $size = 12345;
        
        $result = $this->indexManager->write($path, $size);
        
        $this->assertTrue($result);
        
        // Verify the entry was stored with metadata
        $index = $this->indexManager->read($path);
        $this->assertIsArray($index);
        $this->assertCount(1, $index);
        $this->assertArrayHasKey('path', $index[0]);
        $this->assertArrayHasKey('size', $index[0]);
        $this->assertEquals(12345, $index[0]['size']);
    }

    #[TestDox('write stores file with default size when size not provided')]
    public function testWriteStoresFileWithDefaultSize(): void
    {
        $path = 'uploads/2024/02/test-image.jpg';
        
        $result = $this->indexManager->write($path);
        
        $this->assertTrue($result);
        
        // Write another file to the same index to test it appends
        $result2 = $this->indexManager->write('uploads/2024/02/another-image.jpg');
        $this->assertTrue($result2);
        
        // Verify the entry was stored with default size of 0
        $index = $this->indexManager->read($path);
        $this->assertIsArray($index);
        $this->assertCount(2, $index); // Should have two entries now
        $this->assertArrayHasKey('path', $index[0]);
        $this->assertArrayHasKey('size', $index[0]);
        $this->assertEquals(0, $index[0]['size']);
    }

    #[TestDox('read throws exception when index does not exist')]
    public function testReadThrowsExceptionWhenIndexDoesNotExist(): void
    {
        $this->expectException(Exception\IndexNotFoundException::class);
        $this->indexManager->read('uploads/2099/12/nonexistent.jpg');
    }

    #[TestDox('delete removes file from index with old string format')]
    public function testDeleteRemovesFileFromOldFormat(): void
    {
        // Create index with old string format manually
        $path = 'uploads/2024/03/test-image.jpg';
        $details = $this->pathParser->getPathDetails($path);
        $file = $this->fileSystem->getCacheFilePath($details);
        
        // Write old format (array of strings) - paths are normalized (no bucket prefix)
        $oldFormatIndex = [
            'uploads/2024/03/test-image.jpg',
            'uploads/2024/03/another-image.jpg'
        ];
        $this->fileSystem->filePutContents($file, json_encode($oldFormatIndex));
        
        // Delete first entry
        $result = $this->indexManager->delete($path);
        
        $this->assertTrue($result);
        
        // Verify it was removed
        $index = $this->indexManager->read($path);
        $this->assertCount(1, $index);
        
        // Remaining entry should still be accessible
        $remainingPath = is_array($index[0]) ? $index[0]['path'] : $index[0];
        $this->assertEquals('uploads/2024/03/another-image.jpg', $remainingPath);
    }

    #[TestDox('delete removes file from index with new metadata format')]
    public function testDeleteRemovesFileFromNewFormat(): void
    {
        $path1 = 'uploads/2024/04/test-image.jpg';
        $path2 = 'uploads/2024/04/another-image.jpg';
        
        // Add two files with metadata
        $this->indexManager->write($path1, 1000);
        $this->indexManager->write($path2, 2000);
        
        // Delete first file
        $result = $this->indexManager->delete($path1);
        
        $this->assertTrue($result);
        
        // Verify only second file remains
        $index = $this->indexManager->read($path2);
        $this->assertCount(1, $index);
        $this->assertEquals('uploads/2024/04/another-image.jpg', $index[0]['path']);
        $this->assertEquals(2000, $index[0]['size']);
    }

    #[TestDox('write and read work correctly with multisite paths')]
    public function testWriteAndReadWorkWithMultisitePaths(): void
    {
        $path = 'uploads/networks/1/sites/5/uploads/2024/05/test-image.jpg';
        $size = 54321;
        
        $result = $this->indexManager->write($path, $size);
        
        $this->assertTrue($result);
        
        // Verify the entry was stored correctly
        $index = $this->indexManager->read($path);
        $this->assertIsArray($index);
        $this->assertCount(1, $index);
        $this->assertEquals(54321, $index[0]['size']);
    }
}
