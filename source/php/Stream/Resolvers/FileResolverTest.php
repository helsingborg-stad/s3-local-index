<?php

namespace S3_Local_Index\Stream\Resolvers;

use PHPUnit\Framework\TestCase;
use S3_Local_Index\Index\IndexManager;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Parser\PathParser;
use WpService\Implementations\FakeWpService;

class FileResolverTest extends TestCase
{
    public function testIndexMissDelegatesToS3(): void
    {
        $index = $this->createMock(IndexManager::class);
        $index->method('read')->willReturn([]);
        $resolver = new FileResolver(
            new FakeWpService([]),
            $this->createMock(LoggerInterface::class),
            new PathParser(),
            $index,
        );

        $path = 'helsingborg-se/uploads/networks/1/2026/08/image.jpg';
        $this->assertTrue($resolver->canResolve($path, STREAM_URL_STAT_QUIET));
        $this->assertNull($resolver->url_stat($path, STREAM_URL_STAT_QUIET));
    }
}
