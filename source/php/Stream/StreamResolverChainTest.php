<?php

namespace S3_Local_Index\Stream;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use S3_Local_Index\Logger\LoggerInterface;

class StreamResolverChainTest extends TestCase
{
    private StreamResolverChain $chain;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->chain = new StreamResolverChain($this->logger);
    }

    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $this->assertInstanceOf(StreamResolverChain::class, $this->chain);
        $this->assertInstanceOf(StreamResolverInterface::class, $this->chain);
    }

    #[TestDox('addResolver adds resolver to chain')]
    public function testAddResolverAddsResolverToChain(): void
    {
        $resolver = $this->createMock(StreamResolverInterface::class);
        
        $result = $this->chain->addResolver($resolver);
        
        $this->assertSame($this->chain, $result); // Fluent interface
        $this->assertContains($resolver, $this->chain->getResolvers());
    }

    #[TestDox('canResolve returns false when no resolvers')]
    public function testCanResolveReturnsFalseWhenNoResolvers(): void
    {
        $uri = 'test://example.com/file.txt';
        $flags = 0;
        
        $result = $this->chain->canResolve($uri, $flags);
        
        $this->assertFalse($result);
    }

    #[TestDox('canResolve returns true when resolver can handle request')]
    public function testCanResolveReturnsTrueWhenResolverCanHandle(): void
    {
        $uri = 'test://example.com/file.txt';
        $flags = 0;
        
        $resolver = $this->createMock(StreamResolverInterface::class);
        $resolver->expects($this->once())
                 ->method('canResolve')
                 ->with($uri, $flags)
                 ->willReturn(true);
        
        $this->chain->addResolver($resolver);
        
        $result = $this->chain->canResolve($uri, $flags);
        
        $this->assertTrue($result);
    }

    #[TestDox('resolve delegates to first capable resolver')]
    public function testResolveDelegatesToFirstCapableResolver(): void
    {
        $uri = 'test://example.com/file.txt';
        $flags = 0;
        $expectedResult = ['size' => 123];
        
        $resolver1 = $this->createMock(StreamResolverInterface::class);
        $resolver1->expects($this->once())
                  ->method('canResolve')
                  ->with($uri, $flags)
                  ->willReturn(false);
        
        $resolver2 = $this->createMock(StreamResolverInterface::class);
        $resolver2->expects($this->once())
                  ->method('canResolve')
                  ->with($uri, $flags)
                  ->willReturn(true);
        $resolver2->expects($this->once())
                  ->method('resolve')
                  ->with($uri, $flags)
                  ->willReturn($expectedResult);
        
        $this->chain->addResolver($resolver1)->addResolver($resolver2);
        
        $result = $this->chain->resolve($uri, $flags);
        
        $this->assertEquals($expectedResult, $result);
    }
}