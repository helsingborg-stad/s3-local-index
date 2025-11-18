<?php

namespace S3_Local_Index\Stream\Response;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

class ResponseTraitTest extends TestCase
{
    use ResponseTrait;

    #[TestDox('url_stat_response returns ResponseTraitUrlStatInterface')]
    public function testUrlStatResponseReturnsInterface(): void
    {
        $response = $this->url_stat_response();
        
        $this->assertInstanceOf(ResponseTraitUrlStatInterface::class, $response);
    }

    #[TestDox('found returns stat array with default size for file')]
    public function testFoundReturnsStatArrayWithDefaultSize(): void
    {
        $response = $this->url_stat_response();
        $stat = $response->found('file');
        
        $this->assertIsArray($stat);
        $this->assertArrayHasKey('size', $stat);
        $this->assertEquals(1024, $stat['size']); // Default size
    }

    #[TestDox('found returns stat array with custom size for file')]
    public function testFoundReturnsStatArrayWithCustomSize(): void
    {
        $response = $this->url_stat_response();
        $customSize = 54321;
        $stat = $response->found('file', $customSize);
        
        $this->assertIsArray($stat);
        $this->assertArrayHasKey('size', $stat);
        $this->assertEquals($customSize, $stat['size']);
    }

    #[TestDox('found returns stat array with zero size for directory')]
    public function testFoundReturnsStatArrayWithZeroSizeForDirectory(): void
    {
        $response = $this->url_stat_response();
        $stat = $response->found('dir');
        
        $this->assertIsArray($stat);
        $this->assertArrayHasKey('size', $stat);
        $this->assertEquals(0, $stat['size']); // Directories always have size 0
    }

    #[TestDox('found returns stat array with all required fields')]
    public function testFoundReturnsStatArrayWithAllRequiredFields(): void
    {
        $response = $this->url_stat_response();
        $stat = $response->found('file', 12345);
        
        // Verify all stat fields are present
        $requiredFields = [
            'dev', 'ino', 'mode', 'nlink', 'uid', 'gid', 
            'rdev', 'size', 'atime', 'mtime', 'ctime', 
            'blksize', 'blocks'
        ];
        
        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $stat, "Stat array missing field: {$field}");
        }
    }

    #[TestDox('found calculates blocks correctly based on size')]
    public function testFoundCalculatesBlocksCorrectly(): void
    {
        $response = $this->url_stat_response();
        
        // Test with size that's not evenly divisible by 512
        $size = 1000;
        $stat = $response->found('file', $size);
        $expectedBlocks = ceil($size / 512);
        
        $this->assertEquals($expectedBlocks, $stat['blocks']);
        
        // Test with size that's evenly divisible by 512
        $size = 1024;
        $stat = $response->found('file', $size);
        $expectedBlocks = ceil($size / 512);
        
        $this->assertEquals($expectedBlocks, $stat['blocks']);
    }

    #[TestDox('bypass returns null')]
    public function testBypassReturnsNull(): void
    {
        $response = $this->url_stat_response();
        $result = $response->bypass();
        
        $this->assertNull($result);
    }

    #[TestDox('notfound returns false')]
    public function testNotfoundReturnsFalse(): void
    {
        $response = $this->url_stat_response();
        $result = $response->notfound();
        
        $this->assertFalse($result);
    }

    #[TestDox('found throws exception for invalid type')]
    public function testFoundThrowsExceptionForInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Type must be either "file" or "dir".');
        
        $response = $this->url_stat_response();
        $response->found('invalid');
    }
}
