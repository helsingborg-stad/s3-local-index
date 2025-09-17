<?php

namespace S3_Local_Index\Stream;

/**
 * Interface for S3 stream wrapper with local index support.
 *
 * Defines the required methods for a stream wrapper implementation.
 */
interface WrapperInterface
{
  /**
   * Wrapper method to add index lookup to url_stat.
   *
   * This method first checks the local index for file existence
   * before delegating to the original S3 stream wrapper.
   *
   * @param  string $path   The file URI to check.
   * @param  int    $flags  Flags for the stat operation.
   *
   * @return array|false File statistics array or false if the file doesn't exist.
   */
  public function url_stat($path, $flags): array|false;

  /**
   * Flushes the output for the stream and updates the local index accordingly.
   *
   * This method performs the underlying stream flush and then adds or updates
   * the file entry in the local index.
   *
   * @return bool True on success, false on failure.
   */
  public function stream_flush(): bool;

  /**
   * Deletes a file at the given path and removes it from the local index.
   *
   * This method performs the underlying unlink operation and then removes
   * the file entry from the local index.
   *
   * @param string $path The file path to delete.
   * @return bool True on success, false on failure.
   */
  public function unlink(string $path): bool;
}