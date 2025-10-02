<?php

namespace S3_Local_Index\Stream;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Parser\PathParserInterface;
use S3_Local_Index\Index\IndexManager;

class S3LocalIndexResolverTest extends TestCase
{
    private S3LocalIndexResolver $resolver;
    private CacheInterface $cache;
    private FileSystemInterface $fileSystem;
    private LoggerInterface $logger;
    private PathParserInterface $pathParser;
    private IndexManager $indexManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cache = $this->createMock(CacheInterface::class);
        $this->fileSystem = $this->createMock(FileSystemInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->pathParser = $this->createMock(PathParserInterface::class);
        $this->indexManager = $this->createMock(IndexManager::class);
        
        $this->resolver = new S3LocalIndexResolver(
            $this->cache,
            $this->fileSystem,
            $this->logger,
            $this->pathParser,
            $this->indexManager
        );
    }

    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $this->assertInstanceOf(S3LocalIndexResolver::class, $this->resolver);
        $this->assertInstanceOf(StreamResolverInterface::class, $this->resolver);
        $this->assertInstanceOf(WrapperInterface::class, $this->resolver);
    }

    #[TestDox('canResolve returns true for file existence checks')]
    public function testCanResolveReturnsTrueForFileExistenceChecks(): void
    {
        $uri = 'uploads/2023/01/image.jpg';
        $flags = STREAM_URL_STAT_QUIET;
        
        $this->pathParser
            ->expects($this->once())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($uri);
        
        $result = $this->resolver->canResolve($uri, $flags);
        
        $this->assertTrue($result);
    }

    #[TestDox('canResolve returns false for non-file paths')]
    public function testCanResolveReturnsFalseForNonFilePaths(): void
    {
        $uri = 'uploads/2023/01/';  // No file extension
        $flags = STREAM_URL_STAT_QUIET;
        
        $this->pathParser
            ->expects($this->once())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($uri);
        
        $result = $this->resolver->canResolve($uri, $flags);
        
        $this->assertFalse($result);
    }

    #[TestDox('canResolve returns false without STREAM_URL_STAT_QUIET flag')]
    public function testCanResolveReturnsFalseWithoutQuietFlag(): void
    {
        $uri = 'uploads/2023/01/image.jpg';
        $flags = 0; // No STREAM_URL_STAT_QUIET flag
        
        $this->pathParser
            ->expects($this->once())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($uri);
        
        $result = $this->resolver->canResolve($uri, $flags);
        
        $this->assertFalse($result);
    }
}