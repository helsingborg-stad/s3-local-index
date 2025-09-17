<?php

namespace S3_Local_Index\Stream;

use PHPUnit\Framework\TestCase;
use S3_Local_Index\Logger\LoggerInterface;

class WrapperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @testdox class can be instantiated
     */
    public function testClassCanBeInstantiated(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger);

        $this->assertInstanceOf(Wrapper::class, $wrapper);
    }

    /**
     * @testdox init method does not throw an exception
     */
    public function testInitMethodDoesNotThrowException(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger);

        try {
            $wrapper->init();
            $this->assertTrue(true, 'init method executed without exceptions.');
        } catch (\Exception $e) {
            $this->fail('init method threw an exception: ' . $e->getMessage());
        }
    }

    /**
     * @testdox stream_open method exists and can be called
     */
    public function testStreamOpenMethodExistsAndCanBeCalled(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger);

        if (method_exists($wrapper, 'stream_open')) {
            try {
                $opened_path = null;
                $result = $wrapper->stream_open('s3://bucket/uploads/2023/01/image.jpg', 'r', 0, $opened_path);
                $this->assertTrue(is_bool($result), 'stream_open should return a boolean.');
            } catch (\Exception $e) {
                $this->fail('stream_open method threw an exception: ' . $e->getMessage());
            }
        } else {
            $this->markTestSkipped('stream_open method not implemented yet.');
        }
    }

    /**
     * @testdox url_stat method exists and can be called
     */
    public function testUrlStatMethodExistsAndCanBeCalled(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger);

        if (method_exists($wrapper, 'url_stat')) {
            try {
                $result = $wrapper->url_stat('s3://bucket/uploads/2023/01/image.jpg', 0);
                $this->assertTrue(is_array($result) || $result === false, 'url_stat should return an array or false.');
            } catch (\Exception $e) {
                $this->fail('url_stat method threw an exception: ' . $e->getMessage());
            }
        } else {
            $this->markTestSkipped('url_stat method not implemented yet.');
        }
    }

    /**
     * @testdox dir_opendir method exists and can be called
     */
    public function testDirOpendirMethodExistsAndCanBeCalled(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger);

        if (method_exists($wrapper, 'dir_opendir')) {
            try {
                $result = $wrapper->dir_opendir('s3://bucket/uploads/2023/01/', 0);
                $this->assertTrue(is_bool($result), 'dir_opendir should return a boolean.');
            } catch (\Exception $e) {
                $this->fail('dir_opendir method threw an exception: ' . $e->getMessage());
            }
        } else {
            $this->markTestSkipped('dir_opendir method not implemented yet.');
        }
    }

    /**
     * @testdox stream_flush method exists and can be called
     */
    public function testStreamFlushMethodExistsAndCanBeCalled(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger);

        if (method_exists($wrapper, 'stream_flush')) {
            try {
                // Create a stream context with s3 options
                $context = stream_context_create([
                    's3' => [
                        'Bucket' => 'test-bucket',
                        'Key' => 'uploads/2023/01/image.jpg'
                    ]
                ]);
                $wrapper->context = $context;
                
                $result = $wrapper->stream_flush();
                $this->assertTrue(is_bool($result), 'stream_flush should return a boolean.');
            } catch (\Exception $e) {
                $this->fail('stream_flush method threw an exception: ' . $e->getMessage());
            }
        } else {
            $this->markTestSkipped('stream_flush method not implemented yet.');
        }
    }

    /**
     * @testdox unlink method exists and can be called
     */
    public function testUnlinkMethodExistsAndCanBeCalled(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger);

        if (method_exists($wrapper, 'unlink')) {
            try {
                $result = $wrapper->unlink('s3://bucket/uploads/2023/01/image.jpg');
                $this->assertTrue(is_bool($result), 'unlink should return a boolean.');
            } catch (\Exception $e) {
                $this->fail('unlink method threw an exception: ' . $e->getMessage());
            }
        } else {
            $this->markTestSkipped('unlink method not implemented yet.');
        }
    }

    private function createReader(): ReaderInterface
    {
        return new class implements ReaderInterface {
            public function loadIndex(string $path): array
            {
                return [];
            }

            public function getCacheKeyForPath(string $path): ?string
            {
                return 'index_1_2023_01';
            }

            public function flushCacheForPath(string $path): bool
            {
                return true;
            }

            public function stream_open(string $path, string $mode, int $options, &$opened_path): bool
            {
                return true;
            }

            public function url_stat(string $path, int $flags): array|false
            {
                return false;
            }

            public function normalize(string $path): string
            {
                return $path;
            }

            public function updateIndex(string $path): bool
            {
                return true;
            }

            public function removeFromIndex(string $path): bool
            {
                return true;
            }

            public function stream_read(int $count): string
            {
                return '';
            }

            public function stream_eof(): bool
            {
                return true;
            }
        };
    }

    private function createLogger(): LoggerInterface
    {
        return new class implements LoggerInterface {
            public function log(string $message): void
            {
                // No action needed for this stub
            }
        };
    }
}