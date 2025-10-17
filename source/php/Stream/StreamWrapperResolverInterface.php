<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Stream\StreamWrapperInterface;

/**
 * Interface for S3 stream wrapper with local index support.
 *
 * Defines the required methods for a stream wrapper implementation.
 */
interface StreamWrapperResolverInterface extends StreamWrapperInterface
{
    public function canResolve(string $path, int $flags): bool;
    public function resolverId(): string;
}