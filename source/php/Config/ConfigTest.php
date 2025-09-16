<?php

namespace S3LocalIndex\Config;

use PHPUnit\Framework\TestCase;
use WpService\Implementations\FakeWpService;

class ConfigTest extends TestCase
{
    /**
     * @testdox class can be instantiated
     */
    public function testClassCanBeInstantiated(): void
    {
        $config = new Config($this->getWpService());

        $this->assertInstanceOf(Config::class, $config);
    }

    /**
     * @testdox isEnabled returns true when S3_Uploads plugin class exists
     */
    public function testIsEnabledReturnsTrueWhenS3UploadsExists(): void
    {
        $config = new Config($this->getWpService());
        
        // Since we mock the S3_Uploads\Plugin class in bootstrap.php, this should return true
        $this->assertTrue($config->isEnabled());
    }

    /**
     * @testdox getCliPriority returns default value
     */
    public function testGetCliPriorityReturnsDefaultValue(): void
    {
        $config = new Config($this->getWpService());
        
        $this->assertEquals(10, $config->getCliPriority());
    }

    /**
     * @testdox getPluginPriority returns default value
     */
    public function testGetPluginPriorityReturnsDefaultValue(): void
    {
        $config = new Config($this->getWpService());
        
        $this->assertEquals(20, $config->getPluginPriority());
    }

    /**
     * @testdox getCacheDirectory returns valid path
     */
    public function testGetCacheDirectoryReturnsValidPath(): void
    {
        $config = new Config($this->getWpService());
        
        $cacheDir = $config->getCacheDirectory();
        $this->assertIsString($cacheDir);
        $this->assertStringContainsString('s3-index-', $cacheDir);
    }

    /**
     * @testdox createFilterKey creates correct filter key
     */
    public function testCreateFilterKeyCreatesCorrectKey(): void
    {
        $config = new Config($this->getWpService());
        
        $filterKey = $config->createFilterKey('testFilter');
        $this->assertEquals('S3LocalIndex/Config/TestFilter', $filterKey);
    }

    /**
     * @testdox createFilterKey with custom prefix creates correct filter key
     */
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