<?php

namespace S3_Local_Index\Index\Maintainance;

use S3_Local_Index\HookableInterface;
use S3_Local_Index\Index\IndexManager;
use S3_Local_Index\Logger\Logger;
use WpService\WpService;

class MaintainIndexOnFileDelete implements HookableInterface
{

  public function __construct(private WpService $wpService, private IndexManager $indexManager, private Logger $logger) {}

  /**
   * Register hooks with WordPress.
   */
  public function addHooks(): void
  {
    $this->wpService->addFilter('wp_delete_file', [$this, 'onFileDelete'], 100, 1);
  }

  /**
   * Handle file delete event.
   *
   * @param string $file
   * @return string
   */
  public function onFileDelete(string $file): string
  {
    $this->logger->log("[MaintainIndex][wp_delete_file]: Hook triggered to delete {$file} from index.");
    $this->indexManager->delete($file);
    return $file;
  }
}