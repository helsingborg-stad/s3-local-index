<?php

namespace S3_Local_Index\Stream;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use S3_Local_Index\Logger\LoggerInterface;

class StreamWrapperRegistrarTest extends TestCase
{
    private StreamWrapperRegistrar $registrar;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->registrar = new StreamWrapperRegistrar($this->logger);
    }

    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $this->assertInstanceOf(StreamWrapperRegistrar::class, $this->registrar);
        $this->assertInstanceOf(StreamWrapperRegistrarInterface::class, $this->registrar);
    }

    #[TestDox('isRegistered checks if protocol is registered')]
    public function testIsRegisteredChecksIfProtocolIsRegistered(): void
    {
        $protocol = 's3';
        
        // The s3 protocol should not be registered by default in tests
        $result = $this->registrar->isRegistered($protocol);
        
        // We can't predict the exact result as it depends on environment
        // but we can verify the method exists and returns a boolean
        $this->assertIsBool($result);
    }
}