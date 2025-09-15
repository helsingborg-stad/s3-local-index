<?php

namespace S3LocalIndex;

use WpService\WpService;
use S3LocalIndex\Config\ConfigInterface;
use WP_CLI;
use S3_Local_Index\CLI\Command;
use S3_Local_Index\Stream\Wrapper;
use S3_Local_Index\Stream\Reader;
use S3_Local_Index\Cache\CacheFactory;

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
    WP_CLI::add_command('s3-index', Command::class);
  }

  /**
   * Initialize the plugin functionality.
   *
   * @return void
   */
  public function initPlugin(): void
  {
    Reader::setCache(CacheFactory::createDefault());
    Wrapper::init();
  }
}
