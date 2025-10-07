<?php

namespace S3_Local_Index\Stream;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Parser\PathParserInterface;

class StreamWrapperProxyTest extends TestCase
{
    private StreamWrapperProxy $streamWrapperProxy;
    private StreamWrapperInterface $mockOriginal;
    private PathParserInterface $mockPathParser;
    /** @var StreamWrapperResolverInterface[] */
    private array $mockIndexedResolvers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockIndexedResolvers = [
            $this->createMockStreamWrapper(),
            $this->createMockStreamWrapper()
        ];
        $this->mockOriginal = $this->createMockStreamWrapper();
        $this->mockPathParser = $this->createMockPathParser();

        $this->streamWrapperProxy = new StreamWrapperProxy();
        $this->streamWrapperProxy::setDependencies(
            $this->mockPathParser,
            $this->mockOriginal,
            ...$this->mockIndexedResolvers
        );
    }

    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $proxy = new StreamWrapperProxy();
        $this->assertInstanceOf(StreamWrapperProxy::class, $proxy);
    }

    #[TestDox('url_stat delegates non-file_exists queries to original wrapper')]
    public function testUrlStatDelegatesNonFileExistsQueriesToOriginal(): void
    {
        $uri = 's3://bucket/path/directory/';
        $flags = 0; // No STREAM_URL_STAT_QUIET flag

        $normalizedUri = 'bucket/path/directory/';
        $this->mockPathParser->expects($this->once())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($normalizedUri);

        $this->mockPathParser->expects($this->once())
            ->method('normalizePathWithProtocol')
            ->with($normalizedUri)
            ->willReturn('s3://' . $normalizedUri);

        $expectedStats = ['size' => 1024, 'mtime' => time()];
        $this->mockOriginal->expects($this->once())
            ->method('url_stat')
            ->with('s3://' . $normalizedUri, $flags)
            ->willReturn($expectedStats);

        $result = $this->streamWrapperProxy->url_stat($uri, $flags);

        $this->assertEquals($expectedStats, $result);
    }

    #[TestDox('url_stat uses indexed wrapper for file_exists queries')]
    public function testUrlStatUsesIndexedWrapperForFileExistsQueries(): void
    {
        $uri = 's3://bucket/path/file.jpg';
        $flags = STREAM_URL_STAT_QUIET; // file_exists check

        $normalizedUri = 'bucket/path/file.jpg';
        $this->mockPathParser->expects($this->once())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($normalizedUri);

        // First resolver returns false, second returns stats
        $this->mockIndexedResolvers[0]->expects($this->once())
            ->method('url_stat')
            ->with($normalizedUri, $flags)
            ->willReturn(false);

        $expectedStats = ['size' => 1024, 'mtime' => time()];
        $this->mockIndexedResolvers[1]->expects($this->once())
            ->method('url_stat')
            ->with($normalizedUri, $flags)
            ->willReturn($expectedStats);

        $result = $this->streamWrapperProxy->url_stat($uri, $flags);

        $this->assertEquals($expectedStats, $result);
    }

    #[TestDox('url_stat falls back to original when indexed fails')]
    public function testUrlStatFallsBackToOriginalWhenIndexedFails(): void
    {
        $uri = 's3://bucket/path/file.jpg';
        $flags = STREAM_URL_STAT_QUIET;

        $normalizedUri = 'bucket/path/file.jpg';
        $this->mockPathParser->expects($this->atLeastOnce())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($normalizedUri);

        $this->mockPathParser->expects($this->once())
            ->method('normalizePathWithProtocol')
            ->with($normalizedUri)
            ->willReturn('s3://' . $normalizedUri);

        // Both resolvers throw exception
        $this->mockIndexedResolvers[0]->expects($this->once())
            ->method('url_stat')
            ->with($normalizedUri, $flags)
            ->willThrowException(new \Exception('Index lookup failed'));
        $this->mockIndexedResolvers[1]->expects($this->once())
            ->method('url_stat')
            ->with($normalizedUri, $flags)
            ->willThrowException(new \Exception('Index lookup failed'));

        $expectedStats = ['size' => 1024, 'mtime' => time()];
        $this->mockOriginal->expects($this->once())
            ->method('url_stat')
            ->with('s3://' . $normalizedUri, $flags)
            ->willReturn($expectedStats);

        $result = $this->streamWrapperProxy->url_stat($uri, $flags);

        $this->assertEquals($expectedStats, $result);
    }

    #[TestDox('url_stat returns false when entry not found')]
    public function testUrlStatReturnsFalseWhenEntryNotFound(): void
    {
        $uri = 's3://bucket/path/file.jpg';
        $flags = STREAM_URL_STAT_QUIET;

        $normalizedUri = 'bucket/path/file.jpg';
        $this->mockPathParser->expects($this->once())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($normalizedUri);

        // Both resolvers return false (entry not found)
        $this->mockIndexedResolvers[0]->expects($this->once())
            ->method('url_stat')
            ->with($normalizedUri, $flags)
            ->willReturn(false);
        $this->mockIndexedResolvers[1]->expects($this->once())
            ->method('url_stat')
            ->with($normalizedUri, $flags)
            ->willReturn(false);

        $result = $this->streamWrapperProxy->url_stat($uri, $flags);

        $this->assertFalse($result);
    }

    private function createMockStreamWrapper(): StreamWrapperResolverInterface
    {
        return $this->createMock(StreamWrapperResolverInterface::class);
    }

    private function createMockPathParser(): PathParserInterface
    {
        return $this->createMock(PathParserInterface::class);
    }

}