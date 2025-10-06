<?php

namespace S3_Local_Index\Stream;

/**
 * Interface for S3 stream wrapper with local index support.
 *
 * Defines the required methods for a stream wrapper implementation.
 */
interface StreamWrapperResolverInterface
{
  public function canResolve(string $path, int $flags): bool;
  public function resolverId(): string;
}