<?php

namespace S3LocalIndex;

use WpService\WpService;
use S3LocalIndex\Config\ConfigInterface;
use S3_Local_Index\FileSystem\NativeFileSystem;
use WP_CLI;
use S3_Local_Index\CLI\Command;
use S3_Local_Index\Stream\Wrapper;
use S3_Local_Index\Cache\CacheFactory;
use S3_Uploads\Plugin as S3Plugin;
use S3_Local_Index\Stream\Reader;
use S3_Local_Index\Logger\Logger;
use S3LocalIndex\Parser\Parser;
use S3_Local_Index\Stream\WrapperOriginal;

/**
 * Main application class for S3 Local Index plugin.
 * 
 * This class orchestrates the initialization of the plugin's components,
 * including CLI commands and stream wrapper functionality. It implements
 * the HookableInterface to integrate with WordPress hooks.
 */
class App implements HookableInterface
{
    /**
     * Constructor for the main application.
     *
     * @param WpService       $wpService The WordPress service provider
     * @param ConfigInterface $config    The configuration provider
     */
    public function __construct(private WpService $wpService, private ConfigInterface $config)
    {
    }

    /**
     * Add hooks to WordPress.
     * 
     * Registers WordPress action hooks for CLI and plugin initialization
     * if the plugin is enabled according to configuration.
     * 
     * @return void
     */
    public function addHooks(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $this->wpService->addAction(
            'cli_init', [$this, 'initCli'], 
            $this->config->getCliPriority()
        );

        $this->wpService->addAction(
            'plugins_loaded', [$this, 'initPlugin'], 
            $this->config->getPluginPriority()
        );
    }

    /**
     * Initialize the CLI commands.
     *
     * Sets up the WP-CLI command interface for creating s3 index.
     *
     * @return void
     */
    public function initCli(): void
    {
        $fileSystem = new NativeFileSystem($this->config);
        $parser = new Parser();
        $logger = new Logger();
        $cacheFactory = new CacheFactory($this->wpService);
    
        $cliCommand = new Command(
            $this->wpService,
            S3Plugin::get_instance(),
            WP_CLI::class,
            $fileSystem,
            $cacheFactory,
            $parser,
            $logger
        );
        WP_CLI::add_command('s3-index', $cliCommand);
    }

    /**
     * Initialize the plugin functionality.
     *
     * Sets up the stream wrapper that provides transparent access to S3 files
     * through the WordPress filesystem API, with caching support.
     *
     * @return void
     */
    public function initPlugin(): void
    {
        $fileSystem   = new NativeFileSystem($this->config);
        $parser       = new Parser();
        $cache        = (new CacheFactory($this->wpService))->createDefault();
        $logger       = new Logger();
        $reader       = new Reader($cache, $fileSystem, $logger, $parser);
        $streamWrapperOriginal = new WrapperOriginal();

        //Setup and register the stream wrapper
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $parser, $logger, $streamWrapperOriginal);
        $wrapper->register();
    }
}
