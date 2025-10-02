<?php

namespace S3_Local_Index\Stream;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Parser\PathParserInterface;

class S3StreamWrapperProxyTest extends TestCase
{
    private S3StreamWrapperProxy $proxy;
    private StreamResolverChain $resolverChain;
    private PathParserInterface $pathParser;
    private LoggerInterface $logger;
    private WrapperInterface $originalWrapper;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->resolverChain = $this->createMock(StreamResolverChain::class);
        $this->pathParser = $this->createMock(PathParserInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->originalWrapper = $this->createMock(WrapperInterface::class);
        
        $this->proxy = new S3StreamWrapperProxy();
        $this->proxy->setDependencies(
            $this->resolverChain,
            $this->pathParser,
            $this->logger,
            $this->originalWrapper
        );
    }

    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $this->assertInstanceOf(S3StreamWrapperProxy::class, $this->proxy);
        $this->assertInstanceOf(WrapperInterface::class, $this->proxy);
    }

    #[TestDox('url_stat delegates to resolver chain when available')]
    public function testUrlStatDelegatesToResolverChain(): void
    {
        $uri = 'uploads/2023/01/image.jpg';
        $flags = STREAM_URL_STAT_QUIET;
        $normalizedUri = 'uploads/2023/01/image.jpg';
        $expectedResult = ['size' => 123];
        
        $this->pathParser
            ->expects($this->once())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($normalizedUri);
        
        $this->resolverChain
            ->expects($this->once())
            ->method('canResolve')
            ->with($normalizedUri, $flags)
            ->willReturn(true);
        
        $this->resolverChain
            ->expects($this->once())
            ->method('resolve')
            ->with($normalizedUri, $flags)
            ->willReturn($expectedResult);
        
        $result = $this->proxy->url_stat($uri, $flags);
        
        $this->assertEquals($expectedResult, $result);
    }

    #[TestDox('url_stat delegates to original wrapper when resolver chain cannot handle')]
    public function testUrlStatDelegatesToOriginalWrapperWhenChainCannotHandle(): void
    {
        $uri = 'uploads/2023/01/image.jpg';
        $flags = 0;
        $normalizedUri = 'uploads/2023/01/image.jpg';
        $normalizedWithProtocol = 's3://bucket/uploads/2023/01/image.jpg';
        $expectedResult = false;
        
        $this->pathParser
            ->expects($this->once())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($normalizedUri);
        
        $this->pathParser
            ->expects($this->once())
            ->method('normalizePathWithProtocol')
            ->with($uri)
            ->willReturn($normalizedWithProtocol);
        
        $this->resolverChain
            ->expects($this->once())
            ->method('canResolve')
            ->with($normalizedUri, $flags)
            ->willReturn(false);
        
        $this->originalWrapper
            ->expects($this->once())
            ->method('url_stat')
            ->with($normalizedWithProtocol, $flags)
            ->willReturn($expectedResult);
        
        $result = $this->proxy->url_stat($uri, $flags);
        
        $this->assertEquals($expectedResult, $result);
    }

    #[TestDox('url_stat returns false when resolver returns entry_not_found')]
    public function testUrlStatReturnsFalseWhenResolverReturnsEntryNotFound(): void
    {
        $uri = 'uploads/2023/01/image.jpg';
        $flags = STREAM_URL_STAT_QUIET;
        $normalizedUri = 'uploads/2023/01/image.jpg';
        
        $this->pathParser
            ->expects($this->once())
            ->method('normalizePath')
            ->with($uri)
            ->willReturn($normalizedUri);
        
        $this->resolverChain
            ->expects($this->once())
            ->method('canResolve')
            ->with($normalizedUri, $flags)
            ->willReturn(true);
        
        $this->resolverChain
            ->expects($this->once())
            ->method('resolve')
            ->with($normalizedUri, $flags)
            ->willReturn('entry_not_found');
        
        $result = $this->proxy->url_stat($uri, $flags);
        
        $this->assertFalse($result);
    }
}