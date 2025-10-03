<?php

namespace S3_Local_Index\Index\Maintainance;

use S3_Local_Index\HookableInterface;

class MaintainIndexOnFileUpload implements HookableInterface
{

  public function __construct(private $wpService, private $indexManager) {}

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
   * @param array $upload
   * @param string $context
   */
  public function onFileUpload(array $postId): void
  {
    $isImage = $this->wpService->wpAttachmentIsImage($postId);
    if (!$isImage) {
      return;
    }
    $attachmentUrl = $this->wpService->getAttachedFile($postId);
    if (!$attachmentUrl) {
      return;
    }
    $this->indexManager->write($attachmentUrl);
  }
}