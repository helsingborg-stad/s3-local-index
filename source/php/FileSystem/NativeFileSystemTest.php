<?php

namespace S3_Local_Index\FileSystem;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use S3_Local_Index\Config\ConfigInterface;

class NativeFileSystemTest extends TestCase
{
    private string $testDir;
    private string $testFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/s3-index-test-' . uniqid();
        $this->testFile = $this->testDir . '/test-file.txt';
        
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
        if (is_dir($this->testDir)) {
            rmdir($this->testDir);
        }
        parent::tearDown();
    }

    #[TestDox('class can be instantiated without config')]
    public function testClassCanBeInstantiatedWithoutConfig(): void
    {
        $fileSystem = new NativeFileSystem();

        $this->assertInstanceOf(NativeFileSystem::class, $fileSystem);
    }

    #[TestDox('class can be instantiated with config')]
    public function testClassCanBeInstantiatedWithConfig(): void
    {
        $fileSystem = new NativeFileSystem($this->getConfig());

        $this->assertInstanceOf(NativeFileSystem::class, $fileSystem);
    }

    #[TestDox('fileExists returns true for existing file')]
    public function testFileExistsReturnsTrueForExistingFile(): void
    {
        $fileSystem = new NativeFileSystem();
        file_put_contents($this->testFile, 'test content');

        $this->assertTrue($fileSystem->fileExists($this->testFile));
    }

    #[TestDox('fileExists returns false for non-existing file')]
    public function testFileExistsReturnsFalseForNonExistingFile(): void
    {
        $fileSystem = new NativeFileSystem();

        $this->assertFalse($fileSystem->fileExists($this->testFile));
    }

    #[TestDox('fileGetContents returns content for existing file')]
    public function testFileGetContentsReturnsContentForExistingFile(): void
    {
        $fileSystem = new NativeFileSystem();
        $content = 'test content';
        file_put_contents($this->testFile, $content);

        $result = $fileSystem->fileGetContents($this->testFile);
        $this->assertEquals($content, $result);
    }

    #[TestDox('fileGetContents returns false for non-existing file')]
    public function testFileGetContentsReturnsFalseForNonExistingFile(): void
    {
        $fileSystem = new NativeFileSystem();

        $result = $fileSystem->fileGetContents($this->testFile);
        $this->assertFalse($result);
    }

    #[TestDox('filePutContents writes content to file')]
    public function testFilePutContentsWritesContentToFile(): void
    {
        $fileSystem = new NativeFileSystem();
        $content = 'test content';

        $result = $fileSystem->filePutContents($this->testFile, $content);
        
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        $this->assertEquals($content, file_get_contents($this->testFile));
    }

    #[TestDox('unlink removes existing file')]
    public function testUnlinkRemovesExistingFile(): void
    {
        $fileSystem = new NativeFileSystem();
        file_put_contents($this->testFile, 'test content');

        $result = $fileSystem->unlink($this->testFile);
        
        $this->assertTrue($result);
        $this->assertFalse(file_exists($this->testFile));
    }

    #[TestDox('getTempDir returns valid directory path')]
    public function testGetTempDirReturnsValidDirectoryPath(): void
    {
        $fileSystem = new NativeFileSystem();

        $tempDir = $fileSystem->getTempDir();
        
        $this->assertIsString($tempDir);
        $this->assertTrue(is_dir($tempDir));
    }

    #[TestDox('getCacheDir returns config directory when config provided')]
    public function testGetCacheDirReturnsConfigDirectoryWhenConfigProvided(): void
    {
        $config = $this->getConfig();
        $fileSystem = new NativeFileSystem($config);

        $cacheDir = $fileSystem->getCacheDir();
        
        $this->assertEquals($this->testDir, $cacheDir);
    }

    #[TestDox('getCacheDir returns temp directory when no config provided')]
    public function testGetCacheDirReturnsTempDirectoryWhenNoConfigProvided(): void
    {
        $fileSystem = new NativeFileSystem();

        $cacheDir = $fileSystem->getCacheDir();
        
        $this->assertEquals(sys_get_temp_dir(), $cacheDir);
    }

    private function getConfig(): ConfigInterface
    {
        return new class($this->testDir) implements ConfigInterface {
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

            public function getCacheFileName(string $path): string
            {
                return 'index_' . md5($path) . '.json';
            }
        };
    }
}
