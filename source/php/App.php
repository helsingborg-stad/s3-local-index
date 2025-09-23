<?php

namespace S3_Local_Index;

use WP_CLI;
use WpService\WpService;
use S3_Local_Index\Config\ConfigInterface;
use S3_Local_Index\FileSystem\NativeFileSystem;
use S3_Local_Index\CLI\Command;
use S3_Local_Index\Stream\Wrapper;
use S3_Local_Index\Cache\CacheFactory;
use S3_Local_Index\Stream\Reader;
use S3_Local_Index\Logger\Logger;
use S3_Local_Index\Parser\PathParser;
use S3_Local_Index\Stream\WrapperOriginal;
use S3_Local_Index\Index\IndexManager;
use S3_Uploads\Plugin as S3Plugin;

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
        $pathParser = new PathParser();
        $logger = new Logger();
        $cacheFactory = new CacheFactory($this->wpService);
    
        $cliCommand = new Command(
            $this->wpService,
            S3Plugin::get_instance(),
            WP_CLI::class,
            $fileSystem,
            $cacheFactory,
            $pathParser,
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
        $pathParser   = new PathParser();
        $cache        = (new CacheFactory($this->wpService))->createDefault();
        $logger       = new Logger();
        
        $indexManager = new IndexManager(
            $cache,
            $fileSystem,
            $logger,
            $pathParser
        );
        $reader       = new Reader($cache, $fileSystem, $logger, $pathParser, $indexManager);
        $streamWrapperOriginal = new WrapperOriginal();

        //Setup and register the stream wrapper
        $wrapper = new Wrapper();
        $wrapper->setDependencies($reader, $pathParser, $logger, $streamWrapperOriginal);
        $wrapper->register();
    }
}
