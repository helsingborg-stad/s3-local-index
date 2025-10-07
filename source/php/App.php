<?php

namespace S3_Local_Index;

use Directory;
use WP_CLI;
use WpService\WpService;
use S3_Local_Index\Config\ConfigInterface;
use S3_Local_Index\FileSystem\NativeFileSystem;
use S3_Local_Index\CLI\Command;
use S3_Local_Index\Stream\StreamWrapperProxy;
use S3_Local_Index\Cache\CacheFactory;
use S3_Local_Index\Logger\Logger;
use S3_Local_Index\Parser\PathParser;
use S3_Local_Index\Index\IndexManager;
use S3_Local_Index\Stream\Resolvers\DirectoryResolver;
use S3_Local_Index\Stream\Resolvers\FileResolver;
use S3_Local_Index\Stream\StreamWrapperOriginal;
use S3_Uploads\Plugin as S3Plugin;
use S3_Local_Index\Stream\StreamWrapperRegistrar;
use S3_Local_Index\Index\Maintainance\MaintainIndexOnFileUpload;
use S3_Local_Index\Index\Maintainance\MaintainIndexOnFileDelete;
use S3_Local_Index\Index\Maintainance\MaintainIndexOnNewIntermidiateImage;

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
        $fileSystem     = new NativeFileSystem($this->config);
        $pathParser     = new PathParser();
        $cache          = (new CacheFactory($this->wpService))->createDefault();
    
        $cliCommand = new Command(
            $this->wpService,
            S3Plugin::get_instance(),
            WP_CLI::class,
            $fileSystem,
            $cache,
            $pathParser
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
        //Create dependencies
        $fileSystem   = new NativeFileSystem($this->config);
        $pathParser   = new PathParser();
        $cache        = (new CacheFactory($this->wpService))->createDefault();
        $logger       = new Logger($this->config);
        $indexManager = new IndexManager($cache, $fileSystem, $logger, $pathParser);

        //Create stream wrappers
        $streamWrapperDirectoryResolver = new DirectoryResolver($this->wpService, $logger, $pathParser, $indexManager);
        $streamWrapperFileResolver      = new FileResolver($this->wpService, $logger, $pathParser, $indexManager);

        $streamWrapperOriginal = new StreamWrapperOriginal();

        //Setup stream wrapper proxy (used by classname in stream wrapper registration)
        (new StreamWrapperProxy())->setDependencies(
            $logger,
            $pathParser,
            $streamWrapperOriginal,
            ...[$streamWrapperDirectoryResolver, $streamWrapperFileResolver]
        );

        //Register a new stream wrapper for s3:// URLs
        $streamWrapperRegistrar = new StreamWrapperRegistrar(
            $logger,
        );
        $streamWrapperRegistrar->unregister('s3');
        $streamWrapperRegistrar->register('s3', StreamWrapperProxy::class);

        //Add hooks to maintain index on file upload/delete
        $maintainIndexOnFileUpload = new MaintainIndexOnFileUpload($this->wpService, $indexManager, $logger);
        $maintainIndexOnFileUpload->addHooks();
        $maintainIndexOnFileDelete = new MaintainIndexOnFileDelete($this->wpService, $indexManager, $logger);
        $maintainIndexOnFileDelete->addHooks();
        $maintainIndexOnNewIntermidiateImage = new MaintainIndexOnNewIntermidiateImage($this->wpService, $indexManager, $logger);
        $maintainIndexOnNewIntermidiateImage->addHooks();
    }
}
