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
    //$this->wpService->addAction('wp_handle_upload', [$this, 'onFileUpload'], 10, 2);
  }

  /**
   * Handle file upload event.
   *
   * @param array $upload
   * @param string $context
   */
  public function onFileUpload(array $upload, string $context): void
  {
    // Implement index maintenance logic here.
  }
}