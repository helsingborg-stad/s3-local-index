<?php

namespace S3_Local_Index\CLI;

use PHPUnit\Framework\TestCase;
use WpService\Implementations\FakeWpService;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\Rebuild\RebuildTracker;
use S3_Local_Index\Cache\CacheFactory;
use S3_Uploads\Plugin;
use WP_CLI;

class CommandTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/s3-cli-test-' . uniqid();
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir, 0777, true);
        }
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
        $command = new Command(
            $this->getWpService(),
            $this->getS3Plugin(),
            $this->getCli(),
            $this->getFileSystem(),
            $this->getRebuildTracker(),
            $this->getCacheFactory()
        );

        $this->assertInstanceOf(Command::class, $command);
    }

    /**
     * @testdox create method does not throw an exception
     */
    public function testCreateMethodDoesNotThrowException(): void
    {
        $command = new Command(
            $this->getWpService(),
            $this->getS3Plugin(),
            $this->getCli(),
            $this->getFileSystem(),
            $this->getRebuildTracker(),
            $this->getCacheFactory()
        );

        try {
            $command->create();
            $this->assertTrue(true, 'create method executed without exceptions.');
        } catch (\Exception $e) {
            $this->fail('create method threw an exception: ' . $e->getMessage());
        }
    }

    /**
     * @testdox flush method does not throw an exception when called without arguments
     */
    public function testFlushMethodDoesNotThrowExceptionWhenCalledWithoutArguments(): void
    {
        $command = new Command(
            $this->getWpService(),
            $this->getS3Plugin(),
            $this->getCli(),
            $this->getFileSystem(),
            $this->getRebuildTracker(),
            $this->getCacheFactory()
        );

        try {
            $command->flush();
            $this->assertTrue(true, 'flush method executed without exceptions.');
        } catch (\Exception $e) {
            $this->fail('flush method threw an exception: ' . $e->getMessage());
        }
    }

    /**
     * @testdox flush method does not throw an exception when called with path argument
     */
    public function testFlushMethodDoesNotThrowExceptionWhenCalledWithPathArgument(): void
    {
        $command = new Command(
            $this->getWpService(),
            $this->getS3Plugin(),
            $this->getCli(),
            $this->getFileSystem(),
            $this->getRebuildTracker(),
            $this->getCacheFactory()
        );

        try {
            $command->flush(['uploads/2023/01/image.jpg']);
            $this->assertTrue(true, 'flush method executed without exceptions.');
        } catch (\Exception $e) {
            $this->fail('flush method threw an exception: ' . $e->getMessage());
        }
    }

    /**
     * @testdox rebuild method does not throw an exception
     */
    public function testRebuildMethodDoesNotThrowException(): void
    {
        $command = new Command(
            $this->getWpService(),
            $this->getS3Plugin(),
            $this->getCli(),
            $this->getFileSystem(),
            $this->getRebuildTracker(),
            $this->getCacheFactory()
        );

        try {
            $command->rebuild();
            $this->assertTrue(true, 'rebuild method executed without exceptions.');
        } catch (\Exception $e) {
            $this->fail('rebuild method threw an exception: ' . $e->getMessage());
        }
    }

    /**
     * @testdox rebuild method with all flag does not throw an exception
     */
    public function testRebuildMethodWithAllFlagDoesNotThrowException(): void
    {
        $command = new Command(
            $this->getWpService(),
            $this->getS3Plugin(),
            $this->getCli(),
            $this->getFileSystem(),
            $this->getRebuildTracker(),
            $this->getCacheFactory()
        );

        try {
            $command->rebuild([], ['all' => true]);
            $this->assertTrue(true, 'rebuild method with all flag executed without exceptions.');
        } catch (\Exception $e) {
            $this->fail('rebuild method with all flag threw an exception: ' . $e->getMessage());
        }
    }

    private function getWpService(): FakeWpService
    {
        return new FakeWpService([
            'addAction' => true,
            'addFilter' => true
        ]);
    }

    private function getS3Plugin(): Plugin
    {
        return new class extends Plugin {
            public static function get_instance() {
                return new static();
            }
            
            public function s3() {
                return new class {
                    public function getPaginator($operation, $args) {
                        // Return empty paginator
                        return new class {
                            public function __construct() {}
                            public function getIterator() {
                                return new \ArrayIterator([]);
                            }
                        };
                    }
                };
            }
            
            public function get_s3_bucket() {
                return 'test-bucket';
            }
        };
    }

    private function getCli(): WP_CLI
    {
        return new class extends WP_CLI {
            public static function log($message) {}
            public static function success($message) {}
            public static function warning($message) {}
        };
    }

    private function getFileSystem(): FileSystemInterface
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

    private function getRebuildTracker(): RebuildTracker
    {
        return new class implements \S3_Local_Index\Rebuild\RebuildTracker {
            public function getRebuildList(): array
            {
                return [];
            }

            public function addToRebuildList(string $blogId, string $year, string $month): bool
            {
                return true;
            }

            public function addPathToRebuildList(string $path): bool
            {
                return true;
            }

            public function clearRebuildList(): bool
            {
                return true;
            }

            public function removeFromRebuildList(string $blogId, string $year, string $month): bool
            {
                return true;
            }
        };
    }

    private function getCacheFactory(): CacheFactory
    {
        return new class($this->getWpService()) extends CacheFactory {
            public function __construct($wpService) {
                // Don't call parent constructor to avoid dependency issues
            }

            public function createDefault(): \S3_Local_Index\Cache\CacheInterface
            {
                return new class implements \S3_Local_Index\Cache\CacheInterface {
                    public function get(string $key) { return null; }
                    public function set(string $key, $data, int $ttl = 0): bool { return true; }
                    public function has(string $key): bool { return false; }
                    public function delete(string $key): bool { return true; }
                    public function clear(): bool { return true; }
                };
            }
        };
    }
}