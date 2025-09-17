<?php

namespace S3LocalIndex\Parser;

class Parser implements ParserInterface
{
  public function __construct()
  {
  }

  /**
   * @inheritDoc
   */
  public function getPathDetails(string $path): ?array
  {
    $path = ltrim($path, '/');
    if (preg_match('#(?:uploads/networks/\d+/sites/(\d+)/)?(?:uploads/)?(\d{4})/(\d{2})/#', $path, $m)) {
        return [
            'blogId' => (int) $m[1] ?: 1,
            'year'   => (int) $m[2],
            'month'  => (int) $m[3],
        ];
    }
    return null;
  }

  /**
   * @inheritDoc
   */
  public function normalizePath(string $path): string
  {
    return ltrim(preg_replace('#^s3://#', '', $path), '/');
  }

  /**
   * @inheritDoc
   */
  public function createCacheIdentifier(array $details): string
  {
    return "index_{$details['blogId']}_{$details['year']}_{$details['month']}";
  }
}