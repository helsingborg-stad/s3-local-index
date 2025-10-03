<?php

namespace S3_Local_Index\Index\Maintainance;

use S3_Local_Index\HookableInterface;
use S3_Local_Index\Index\IndexManager;
use S3_Local_Index\Logger\Logger;
use WpService\WpService;

use S3_Local_Index\Index\Exception\IndexManagerException;

class MaintainIndexOnFileUpload implements HookableInterface
{

  public function __construct(private WpService $wpService, private IndexManager $indexManager, private Logger $logger) {}

  /**
   * Register hooks with WordPress.
   */
  public function addHooks(): void
  {
    $this->wpService->addAction('add_attachment', [$this, 'onFileUpload'], 100, 1);
  }

  /**
   * Handle file upload event.
   *
   * @param int $postId
   */
  public function onFileUpload(int $postId): void
  {
    $isImage = $this->wpService->wpAttachmentIsImage($postId);
    if (!$isImage) {
      return;
    }
    $filePath = $this->wpService->getAttachedFile($postId);
    if (!$filePath) {
      return;
    }

    try {
        $this->indexManager->write($filePath);
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
  }
}