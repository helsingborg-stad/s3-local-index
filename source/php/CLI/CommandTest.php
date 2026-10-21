<?php

namespace S3_Local_Index\CLI\Tests;

class TestS3UploadsPlugin
{
    public function __construct(private object $client, private string $bucket)
    {
    }

    public function s3(): object
    {
        return $this->client;
    }

    public function get_s3_bucket(): string
    {
        return $this->bucket;
    }
}

if (!class_exists(\S3_Uploads\Plugin::class)) {
    class_alias(TestS3UploadsPlugin::class, \S3_Uploads\Plugin::class);
}

namespace S3_Local_Index\CLI;

use PHPUnit\Framework\TestCase;
use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\Parser\PathParserInterface;
use WpService\WpService;

class CommandTest extends TestCase
{
    public function testCreateUsesProvidedBucket(): void
    {
        $s3Client = new class {
            public array $arguments = [];

            public function getPaginator(string $operation, array $arguments): array
            {
                $this->arguments = $arguments;
                return [];
            }
        };
        $fileSystem = $this->createMock(FileSystemInterface::class);
        $fileSystem->method('getCacheDir')->willReturn(sys_get_temp_dir());
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('clear')->willReturn(true);
        $command = new Command(
            $this->createMock(WpService::class),
            new \S3_Uploads\Plugin($s3Client, 'configured-bucket'),
            \WP_CLI::class,
            $fileSystem,
            $cache,
            $this->createMock(PathParserInterface::class)
        );

        $command->create([], ['bucket' => 'requested-bucket']);

        $this->assertSame(['Bucket' => 'requested-bucket'], $s3Client->arguments);
    }

    public function testCreateDefaultsToConfiguredBucket(): void
    {
        $s3Client = new class {
            public array $arguments = [];

            public function getPaginator(string $operation, array $arguments): array
            {
                $this->arguments = $arguments;
                return [];
            }
        };
        $fileSystem = $this->createMock(FileSystemInterface::class);
        $fileSystem->method('getCacheDir')->willReturn(sys_get_temp_dir());
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('clear')->willReturn(true);
        $command = new Command(
            $this->createMock(WpService::class),
            new \S3_Uploads\Plugin($s3Client, 'configured-bucket'),
            \WP_CLI::class,
            $fileSystem,
            $cache,
            $this->createMock(PathParserInterface::class)
        );

        $command->create();

        $this->assertSame(['Bucket' => 'configured-bucket'], $s3Client->arguments);
    }
}
