<?php

namespace S3_Local_Index\Parser;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class PathParserTest extends TestCase
{
    private PathParser $pathParser;

    protected function setUp(): void
    {
        $this->pathParser = new PathParser();
    }

    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $this->assertInstanceOf(PathParser::class, $this->pathParser);
        $this->assertInstanceOf(PathParserInterface::class, $this->pathParser);
    }

    #[TestDox('getPathDetails extracts correct details for single site uploads')]
    public function testGetPathDetailsExtractsCorrectDetailsForSingleSite(): void
    {
        $path = 'uploads/2023/01/image.jpg';
        $result = $this->pathParser->getPathDetails($path);

        $this->assertEquals(
            [
                'blogId' => 1,
                'year' => 2023,
                'month' => 01,
                'networkId' => 1
            ], $result
        );
    }

    #[TestDox('getPathDetails extracts correct details for multisite uploads')]
    public function testGetPathDetailsExtractsCorrectDetailsForMultisite(): void
    {
        $path = 'uploads/networks/1/sites/5/uploads/2023/01/image.jpg';
        $result = $this->pathParser->getPathDetails($path);

        $this->assertEquals(
            [
            'blogId' => 5,
            'year' => 2023,
            'month' => 01,
            'networkId' => 1
            ], $result
        );
    }

    #[TestDox('getPathDetails returns null for invalid pattern')]
    public function testGetPathDetailsReturnsNullForInvalidPattern(): void
    {
        $path = 'some/other/path.jpg';
        $result = $this->pathParser->getPathDetails($path);

        $this->assertNull($result);
    }

    #[TestDox('normalizePath removes s3 protocol and leading slashes')]
    public function testNormalizePathRemovesS3ProtocolAndLeadingSlashes(): void
    {
        $path = 's3://bucket/uploads/2023/01/image.jpg';
        $result = $this->pathParser->normalizePath($path);

        $this->assertEquals('bucket/uploads/2023/01/image.jpg', $result);
    }

    #[TestDox('normalizePath removes leading slashes only when no protocol')]
    public function testNormalizePathRemovesLeadingSlashesOnly(): void
    {
        $path = '/uploads/2023/01/image.jpg';
        $result = $this->pathParser->normalizePath($path);

        $this->assertEquals('uploads/2023/01/image.jpg', $result);
    }

    #[TestDox('createCacheIdentifier creates correct identifier')]
    public function testCreateCacheIdentifierCreatesCorrectIdentifier(): void
    {
        // Test all supported path variants
        $paths = [
            'uploads/2023/01/image.jpg' => 'index_1_2023_01',
            'uploads/networks/1/sites/5/uploads/2023/01/image.jpg' => 'index_5_2023_01',
            'uploads/networks/2/sites/10/uploads/2024/06/photo.png' => 'index_10_2024_06',
        ];

        $cache = new \S3_Local_Index\Cache\StaticCache();

        foreach ($paths as $path => $expectedIdentifier) {
            $pathDetails = $this->pathParser->getPathDetails($path);
            $cacheIdentifier = $cache->createCacheIdentifier($pathDetails);
            $this->assertEquals($expectedIdentifier, $cacheIdentifier);
        }
    }
}
