<?php

namespace S3_Local_Index\Stream;

/**
 * Interface for S3 stream wrapper with local index support.
 *
 * Defines the required methods for a stream wrapper implementation.
 */
interface WrapperInterface
{
  // Stream wrapper: Open file or URL.
  public function stream_open($path, $mode, $options, &$opened_path);

  // Stream wrapper: Read from stream.
  public function stream_read($count);

  // Stream wrapper: Test for end-of-file on stream.
  public function stream_eof();

  // Stream wrapper: Retrieve information about a file.
  public function url_stat($path, $flags);

  // Stream wrapper: Open directory for reading.
  public function dir_opendir($path, $options);

  // Stream wrapper: Read entry from directory.
  public function dir_readdir();

  // Stream wrapper: Close directory handle.
  public function dir_closedir();
}