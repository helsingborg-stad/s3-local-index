<?php

namespace S3_Local_Index\Stream;

/**
 * Interface for stream resolvers that can handle URL stat operations.
 * 
 * Stream resolvers are responsible for determining file existence and
 * providing file statistics for specific types of requests.
 */
interface StreamResolverInterface
{
    /**
     * Check if this resolver can handle the given URI and flags.
     *
     * @param string $uri   The URI to check
     * @param int    $flags The flags for the stat operation
     * @return bool True if this resolver can handle the request
     */
    public function canResolve(string $uri, int $flags): bool;

    /**
     * Resolve file statistics for the given URI.
     *
     * @param string $uri   The URI to resolve
     * @param int    $flags The flags for the stat operation
     * @return array|false|string File statistics array, false if not found, or string for special cases
     */
    public function resolve(string $uri, int $flags): array|false|string;
}