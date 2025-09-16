<?php

namespace S3LocalIndex;

use PHPUnit\Framework\TestCase;
use WpService\Implementations\FakeWpService;
use S3LocalIndex\Config\ConfigInterface;

class AppTest extends TestCase
{
    /**
     * @testdox class can be instantiated
     */
    public function testClassCanBeInstantiated(): void
    {
        $app = new App($this->getWpService(), $this->getConfig());

        $this->assertInstanceOf(App::class, $app);
    }

    /**
     * @testdox addHooks method does not throw an exception when plugin is enabled
     */
    public function testAddHooksMethodDoesNotThrowExceptionWhenEnabled(): void
    {
        $config = $this->getConfig(true);
        $app = new App($this->getWpService(), $config);

        try {
            $app->addHooks();
            $this->assertTrue(true, 'addHooks method executed without exceptions.');
        } catch (\Exception $e) {
            $this->fail('addHooks method threw an exception: ' . $e->getMessage());
        }
    }

    /**
     * @testdox addHooks method does not throw an exception when plugin is disabled
     */
    public function testAddHooksMethodDoesNotThrowExceptionWhenDisabled(): void
    {
        $config = $this->getConfig(false);
        $app = new App($this->getWpService(), $config);

        try {
            $app->addHooks();
            $this->assertTrue(true, 'addHooks method executed without exceptions.');
        } catch (\Exception $e) {
            $this->fail('addHooks method threw an exception: ' . $e->getMessage());
        }
    }

    /**
     * @testdox initCli method does not throw an exception
     */
    public function testInitCliMethodDoesNotThrowException(): void
    {
        $app = new App($this->getWpService(), $this->getConfig());

        try {
            $app->initCli();
            $this->assertTrue(true, 'initCli method executed without exceptions.');
        } catch (\Exception $e) {
            $this->fail('initCli method threw an exception: ' . $e->getMessage());
        }
    }

    /**
     * @testdox initPlugin method does not throw an exception
     */
    public function testInitPluginMethodDoesNotThrowException(): void
    {
        $app = new App($this->getWpService(), $this->getConfig());

        try {
            $app->initPlugin();
            $this->assertTrue(true, 'initPlugin method executed without exceptions.');
        } catch (\Exception $e) {
            $this->fail('initPlugin method threw an exception: ' . $e->getMessage());
        }
    }

    private function getWpService(): FakeWpService
    {
        return new FakeWpService(
            [
            'addAction' => true,
            'addFilter' => true,
            'applyFilters' => function ($filter, $default) {
                return $default; 
            }
            ]
        );
    }

    private function getConfig(bool $enabled = true): ConfigInterface
    {
        return new class($enabled) implements ConfigInterface {
            public function __construct(private bool $enabled)
            {
            }
            
            public function isEnabled(): bool
            {
                return $this->enabled;
            }

            public function getCliPriority(): int
            {
                return 10;
            }

            public function getPluginPriority(): int
            {
                return 20;
            }

            public function getCacheDirectory(): string
            {
                return sys_get_temp_dir() . '/test-cache';
            }
        };
    }
}