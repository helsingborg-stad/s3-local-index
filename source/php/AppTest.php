<?php

namespace S3_Local_Index;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use WpService\Implementations\FakeWpService;
use S3_Local_Index\Config\ConfigInterface;

class AppTest extends TestCase
{
    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $app = new App($this->getWpService(), $this->getConfig());

        $this->assertInstanceOf(App::class, $app);
    }

    #[TestDox('addHooks method does not throw an exception when plugin is enabled')]
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

    #[TestDox('addHooks method does not throw an exception when plugin is disabled')]
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
