<?php

namespace S3_Local_Index\Stream;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use S3_Local_Index\Logger\LoggerInterface;

class WrapperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $s3 = $this->createS3();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger, $s3);

        $this->assertInstanceOf(Wrapper::class, $wrapper);
    }

    #[TestDox('register method does not throw an exception')]
    public function testInitMethodDoesNotThrowException(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $s3 = $this->createS3();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger, $s3);

        try {
            $wrapper->register();
            $this->assertTrue(true, 'register method executed without exceptions.');
        } catch (\Exception $e) {
            $this->fail('register method threw an exception: ' . $e->getMessage());
        }
    }

    #[TestDox('methods can be delegated to the underlying S3 stream wrapper')]
    public function testDirOpendirMethodExistsAndCanBeCalled(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $s3 = $this->createS3();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger, $s3);

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

    #[TestDox('url_stat method exists and can be called')]
    public function testUrlStatMethodExistsAndCanBeCalled(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $s3 = $this->createS3();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger, $s3);

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

    #[TestDox('stream_flush method exists and can be called')]
    public function testStreamFlushMethodExistsAndCanBeCalled(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $s3 = $this->createS3();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger, $s3);

        if (method_exists($wrapper, 'stream_flush')) {
            try {
                // Create a stream context with s3 options
                $context = stream_context_create(
                    [
                    's3' => [
                        'Bucket' => 'test-bucket',
                        'Key' => 'uploads/2023/01/image.jpg'
                    ]
                    ]
                );
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

    #[TestDox('unlink method exists and can be called')]
    public function testUnlinkMethodExistsAndCanBeCalled(): void
    {
        $reader = $this->createReader();
        $logger = $this->createLogger();
        $s3 = $this->createS3();
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $logger, $s3);

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

            public function url_stat(string $path, int $flags): string|array
            {
                return 'not_found';
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

    private function createS3(): WrapperInterface
    {
        return new class implements WrapperInterface {
            public function url_stat(string $path, int $flags)
            {
                return false;
            }

            public function stream_flush(): bool
            {
                return true;
            }

            public function unlink(string $path): bool
            {
                return true;
            }
        };
    } 
}
