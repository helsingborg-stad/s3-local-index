<?php

namespace S3LocalIndex\Parser;

interface ParserInterface
{
  /**
   * Extract details from a given path.
   *
   * @param string $path
   * @return array|null
   */
  public function getPathDetails(string $path): ?array;

  /**
   * Normalize a given path.
   *
   * @param string $path
   * @return string
   */
  public function normalizePath(string $path): string;
}