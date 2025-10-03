<?php

namespace S3_Local_Index\Index\Maintainance;

use S3_Local_Index\HookableInterface;

class MaintainIndexOnFileDelete implements HookableInterface
{

  public function __construct(private $wpService, private $indexManager) {}

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
   * @param array $upload
   * @param string $context
   */
  public function onFileDelete(string $file): string
  {
    $this->indexManager->delete($file);
    return $file;
  }
}