<?php

namespace S3_Local_Index\Config;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use WpService\Implementations\FakeWpService;

class ConfigTest extends TestCase
{
    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $config = new Config($this->getWpService());

        $this->assertInstanceOf(Config::class, $config);
    }

    #[TestDox('isEnabled returns true when S3_Uploads plugin class exists')]
    public function testIsEnabledReturnsTrueWhenS3UploadsExists(): void
    {
        $config = new Config($this->getWpService());
        
        // Since we mock the S3_Uploads\Plugin class in bootstrap.php, this should return true
        $this->assertTrue($config->isEnabled());
    }

    #[TestDox('getCliPriority returns default value')]
    public function testGetCliPriorityReturnsDefaultValue(): void
    {
        $config = new Config($this->getWpService());
        
        $this->assertEquals(10, $config->getCliPriority());
    }

    #[TestDox('getPluginPriority returns default value')]
    public function testGetPluginPriorityReturnsDefaultValue(): void
    {
        $config = new Config($this->getWpService());
        
        $this->assertEquals(20, $config->getPluginPriority());
    }

    #[TestDox('getCacheDirectory returns valid path')]
    public function testGetCacheDirectoryReturnsValidPath(): void
    {
        $config = new Config($this->getWpService());
        
        $cacheDir = $config->getCacheDirectory();
        $this->assertIsString($cacheDir);
        $this->assertStringContainsString('s3-index-', $cacheDir);
    }

    #[TestDox('createFilterKey creates correct filter key')]
    public function testCreateFilterKeyCreatesCorrectKey(): void
    {
        $config = new Config($this->getWpService());
        
        $filterKey = $config->createFilterKey('testFilter');
        $this->assertEquals('S3_Local_Index/Config/TestFilter', $filterKey);
    }

    #[TestDox('createFilterKey with custom prefix creates correct filter key')]
    public function testCreateFilterKeyWithCustomPrefix(): void
    {
        $config = new Config($this->getWpService(), 'CustomPrefix');
        
        $filterKey = $config->createFilterKey('testFilter');
        $this->assertEquals('CustomPrefix/TestFilter', $filterKey);
    }

    private function getWpService(): FakeWpService
    {
        return new FakeWpService(
            [
            'applyFilters' => function ($filter, $default) { 
                return $default; 
            }
            ]
        );
    }
}
