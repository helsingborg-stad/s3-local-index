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
use S3_Local_Index\Rebuild\RebuildTracker;
use S3_Local_Index\Stream\Reader;
use S3_Local_Index\Stream\Directory;

class App implements HookableInterface
{
  public function __construct(private WpService $wpService, private ConfigInterface $config){}

  /**
   * Add hooks to WordPress.
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
   * @return void
   */
  public function initCli(): void
  {
    $fileSystem = new NativeFileSystem();
    $rebuildTracker = new RebuildTracker($fileSystem);
    $cacheFactory = new CacheFactory($this->wpService);
    
    $cliCommand = new Command(
      $this->wpService,
      S3Plugin::class,
      WP_CLI::class,
      $fileSystem,
      $rebuildTracker,
      $cacheFactory
    );
    WP_CLI::add_command('s3-index', $cliCommand);
  }

  /**
   * Initialize the plugin functionality.
   *
   * @return void
   */
  public function initPlugin(): void
  {
    $fileSystem   = new NativeFileSystem();
    $cache        = (new CacheFactory($this->wpService))->createDefault();

    $reader       = new Reader($cache, $fileSystem);
    $directory    = new Directory($reader);
    $wrapper      = new Wrapper($reader, $directory);

    $wrapper->init();
  }
}
