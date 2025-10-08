<?php

namespace S3_Local_Index\Index\Maintainance;

use S3_Local_Index\HookableInterface;
use S3_Local_Index\Index\IndexManager;
use S3_Local_Index\Logger\Logger;
use WpService\WpService;
use S3_Local_Index\Index\Exception\IndexManagerException;

class MaintainIndexOnNewIntermidiateImage implements HookableInterface
{

  public function __construct(private WpService $wpService, private IndexManager $indexManager, private Logger $logger) {}

  /**
   * Register hooks with WordPress.
   */
  public function addHooks(): void
  {
      $this->wpService->addFilter('wp_create_file_in_uploads', [$this, 'onFileCreation'], 1, 1);
  }
  /**
   * Handle file creation event.
   *
   */
  public function onFileCreation($file)
  {
    try {
        $this->indexManager->write($file);
    } catch (IndexManagerException $e) {
      switch ($e->getId()) {
          case 'cannot_write_to_index':
              $this->logger->log("{$e->getMessage()}");
              break;
          default:
              $this->logger->log("Unexpected error on writing to index: {$e->getMessage()}");
              break;
      }
    }
    return $file;
  }
}