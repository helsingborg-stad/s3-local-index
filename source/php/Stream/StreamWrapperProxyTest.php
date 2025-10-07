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
            $this->createMock(LoggerInterface::class),
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
        $uri = 's3://bucket/path/file.txt';
        $flags = STREAM_URL_STAT_QUIET; // Only STREAM_URL_STAT_QUIET flag

        $normalizedUri = 'bucket/path/file.txt';
        $this->mockPathParser->expects($this->once())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($normalizedUri);

        // First resolver cannot handle
        $this->mockIndexedResolvers[0]->expects($this->once())
            ->method('canResolve')
            ->with($normalizedUri, $flags)
            ->willReturn(false);

        // Second resolver can handle and finds the file
        $this->mockIndexedResolvers[1]->expects($this->once())
            ->method('canResolve')
            ->with($normalizedUri, $flags)
            ->willReturn(true);

        $expectedStats = ['size' => 2048, 'mtime' => time()];
        $this->mockIndexedResolvers[1]->expects($this->once())
            ->method('url_stat')
            ->with($normalizedUri, $flags)
            ->willReturn($expectedStats);

        // Original wrapper should not be called
        $this->mockOriginal->expects($this->never())
            ->method('url_stat');

        $result = $this->streamWrapperProxy->url_stat($uri, $flags);

        $this->assertEquals($expectedStats, $result);
    }

    #[TestDox('url_stat falls back to original when indexed fails')]
    public function testUrlStatFallsBackToOriginalWhenIndexedFails(): void
    {
        $uri = 's3://bucket/path/file.txt';
        $flags = STREAM_URL_STAT_QUIET; // Only STREAM_URL_STAT_QUIET flag

        $normalizedUri = 'bucket/path/file.txt';
        $this->mockPathParser->expects($this->once())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($normalizedUri);

        // Both resolvers cannot handle
        foreach ($this->mockIndexedResolvers as $resolver) {
            $resolver->expects($this->once())
                ->method('canResolve')
                ->with($normalizedUri, $flags)
                ->willReturn(false);
        }

        $this->mockPathParser->expects($this->once())
            ->method('normalizePathWithProtocol')
            ->with($normalizedUri)
            ->willReturn('s3://' . $normalizedUri);

        $expectedStats = ['size' => 4096, 'mtime' => time()];
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
        $uri = 's3://bucket/path/missingfile.txt';
        $flags = STREAM_URL_STAT_QUIET; // Only STREAM_URL_STAT_QUIET flag

        $normalizedUri = 'bucket/path/missingfile.txt';
        $this->mockPathParser->expects($this->once())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($normalizedUri);

        // First resolver cannot handle
        $this->mockIndexedResolvers[0]->expects($this->once())
            ->method('canResolve')
            ->with($normalizedUri, $flags)
            ->willReturn(false);

        // Second resolver can handle but does not find the file
        $this->mockIndexedResolvers[1]->expects($this->once())
            ->method('canResolve')
            ->with($normalizedUri, $flags)
            ->willReturn(true);

        $this->mockIndexedResolvers[1]->expects($this->once())
            ->method('url_stat')
            ->with($normalizedUri, $flags)
            ->willReturn(false);

        // Original wrapper should not be called
        $this->mockOriginal->expects($this->never())
            ->method('url_stat');

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