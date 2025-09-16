<?php

namespace S3_Local_Index\Rebuild;

use PHPUnit\Framework\TestCase;
use S3_Local_Index\FileSystem\FileSystemInterface;

class RebuildTrackerTest extends TestCase
{
    private string $testDir;
    private FileSystemInterface $fileSystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/s3-rebuild-test-' . uniqid();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
        $this->fileSystem = $this->createFileSystem();
    }

    protected function tearDown(): void
    {
        $rebuildFile = $this->testDir . '/s3-index-rebuild-list.json';
        if (file_exists($rebuildFile)) {
            unlink($rebuildFile);
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
        $rebuildTracker = new RebuildTracker($this->fileSystem);

        $this->assertInstanceOf(RebuildTracker::class, $rebuildTracker);
    }

    /**
     * @testdox getRebuildList returns empty array when no list exists
     */
    public function testGetRebuildListReturnsEmptyArrayWhenNoListExists(): void
    {
        $rebuildTracker = new RebuildTracker($this->fileSystem);

        $result = $rebuildTracker->getRebuildList();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * @testdox addToRebuildList adds item to list
     */
    public function testAddToRebuildListAddsItemToList(): void
    {
        $rebuildTracker = new RebuildTracker($this->fileSystem);
        
        $result = $rebuildTracker->addToRebuildList('1', '2023', '01');
        
        $this->assertTrue($result);
        
        $rebuildList = $rebuildTracker->getRebuildList();
        $this->assertContains('1-2023-01', $rebuildList);
    }

    /**
     * @testdox addToRebuildList does not add duplicate items
     */
    public function testAddToRebuildListDoesNotAddDuplicateItems(): void
    {
        $rebuildTracker = new RebuildTracker($this->fileSystem);
        
        $rebuildTracker->addToRebuildList('1', '2023', '01');
        $rebuildTracker->addToRebuildList('1', '2023', '01'); // Duplicate
        
        $rebuildList = $rebuildTracker->getRebuildList();
        $this->assertCount(1, $rebuildList);
        $this->assertContains('1-2023-01', $rebuildList);
    }

    /**
     * @testdox addPathToRebuildList works with multisite pattern
     */
    public function testAddPathToRebuildListWorksWithMultisitePattern(): void
    {
        $rebuildTracker = new RebuildTracker($this->fileSystem);
        
        $path = 'uploads/networks/1/sites/5/2023/01/image.jpg';
        $result = $rebuildTracker->addPathToRebuildList($path);
        
        $this->assertTrue($result);
        
        $rebuildList = $rebuildTracker->getRebuildList();
        $this->assertContains('5-2023-01', $rebuildList);
    }

    /**
     * @testdox addPathToRebuildList works with single site pattern
     */
    public function testAddPathToRebuildListWorksWithSingleSitePattern(): void
    {
        $rebuildTracker = new RebuildTracker($this->fileSystem);
        
        $path = 'uploads/2023/01/image.jpg';
        $result = $rebuildTracker->addPathToRebuildList($path);
        
        $this->assertTrue($result);
        
        $rebuildList = $rebuildTracker->getRebuildList();
        $this->assertContains('1-2023-01', $rebuildList);
    }

    /**
     * @testdox addPathToRebuildList handles leading slash
     */
    public function testAddPathToRebuildListHandlesLeadingSlash(): void
    {
        $rebuildTracker = new RebuildTracker($this->fileSystem);
        
        $path = '/uploads/2023/01/image.jpg';
        $result = $rebuildTracker->addPathToRebuildList($path);
        
        $this->assertTrue($result);
        
        $rebuildList = $rebuildTracker->getRebuildList();
        $this->assertContains('1-2023-01', $rebuildList);
    }

    /**
     * @testdox addPathToRebuildList returns false for invalid pattern
     */
    public function testAddPathToRebuildListReturnsFalseForInvalidPattern(): void
    {
        $rebuildTracker = new RebuildTracker($this->fileSystem);
        
        $path = 'invalid/path/structure.jpg';
        $result = $rebuildTracker->addPathToRebuildList($path);
        
        $this->assertFalse($result);
        
        $rebuildList = $rebuildTracker->getRebuildList();
        $this->assertEmpty($rebuildList);
    }

    /**
     * @testdox removeFromRebuildList removes specific item
     */
    public function testRemoveFromRebuildListRemovesSpecificItem(): void
    {
        $rebuildTracker = new RebuildTracker($this->fileSystem);
        
        // Add multiple items
        $rebuildTracker->addToRebuildList('1', '2023', '01');
        $rebuildTracker->addToRebuildList('2', '2023', '02');
        
        // Remove one item
        $result = $rebuildTracker->removeFromRebuildList('1', '2023', '01');
        
        $this->assertTrue($result);
        
        $rebuildList = $rebuildTracker->getRebuildList();
        $this->assertNotContains('1-2023-01', $rebuildList);
        $this->assertContains('2-2023-02', $rebuildList);
    }

    /**
     * @testdox clearRebuildList removes all items
     */
    public function testClearRebuildListRemovesAllItems(): void
    {
        $rebuildTracker = new RebuildTracker($this->fileSystem);
        
        // Add items
        $rebuildTracker->addToRebuildList('1', '2023', '01');
        $rebuildTracker->addToRebuildList('2', '2023', '02');
        
        // Clear list
        $result = $rebuildTracker->clearRebuildList();
        
        $this->assertTrue($result);
        
        $rebuildList = $rebuildTracker->getRebuildList();
        $this->assertEmpty($rebuildList);
    }

    /**
     * @testdox clearRebuildList returns true when no file exists
     */
    public function testClearRebuildListReturnsTrueWhenNoFileExists(): void
    {
        $rebuildTracker = new RebuildTracker($this->fileSystem);
        
        $result = $rebuildTracker->clearRebuildList();
        
        $this->assertTrue($result);
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
        };
    }
}