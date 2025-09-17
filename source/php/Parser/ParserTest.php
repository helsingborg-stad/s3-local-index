<?php

namespace S3LocalIndex\Parser;

use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser();
    }

    /**
     * @testdox class can be instantiated
     */
    public function testClassCanBeInstantiated(): void
    {
        $this->assertInstanceOf(Parser::class, $this->parser);
        $this->assertInstanceOf(ParserInterface::class, $this->parser);
    }

    /**
     * @testdox getPathDetails extracts correct details for single site uploads
     */
    public function testGetPathDetailsExtractsCorrectDetailsForSingleSite(): void
    {
        $path = 'uploads/2023/01/image.jpg';
        $result = $this->parser->getPathDetails($path);

        $this->assertEquals(
            [
            'blogId' => 1,
            'year' => 2023,
            'month' => 1,
            ], $result
        );
    }

    /**
     * @testdox getPathDetails extracts correct details for multisite uploads
     */
    public function testGetPathDetailsExtractsCorrectDetailsForMultisite(): void
    {
        $path = 'uploads/networks/1/sites/5/uploads/2023/01/image.jpg';
        $result = $this->parser->getPathDetails($path);

        $this->assertEquals(
            [
            'blogId' => 5,
            'year' => 2023,
            'month' => 1,
            ], $result
        );
    }

    /**
     * @testdox getPathDetails returns null for invalid pattern
     */
    public function testGetPathDetailsReturnsNullForInvalidPattern(): void
    {
        $path = 'some/other/path.jpg';
        $result = $this->parser->getPathDetails($path);

        $this->assertNull($result);
    }

    /**
     * @testdox normalizePath removes s3 protocol and leading slashes
     */
    public function testNormalizePathRemovesS3ProtocolAndLeadingSlashes(): void
    {
        $path = 's3://bucket/uploads/2023/01/image.jpg';
        $result = $this->parser->normalizePath($path);

        $this->assertEquals('bucket/uploads/2023/01/image.jpg', $result);
    }

    /**
     * @testdox normalizePath removes leading slashes only when no protocol
     */
    public function testNormalizePathRemovesLeadingSlashesOnly(): void
    {
        $path = '/uploads/2023/01/image.jpg';
        $result = $this->parser->normalizePath($path);

        $this->assertEquals('uploads/2023/01/image.jpg', $result);
    }

    /**
     * @testdox createCacheIdentifier creates correct identifier
     */
    public function testCreateCacheIdentifierCreatesCorrectIdentifier(): void
    {
        $details = [
            'blogId' => 5,
            'year' => 2023,
            'month' => 1,
        ];
        $result = $this->parser->createCacheIdentifier($details);

        $this->assertEquals('index_5_2023_01', $result);
    }
}