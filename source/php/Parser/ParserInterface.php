<?php

namespace S3LocalIndex\Parser;

/**
 * Interface defining path parsing operations for S3 file paths.
 */
interface ParserInterface
{
    /**
     * Extract details from a given path.
     *
     * @param  string $path The file path to parse
     * @return array|null   Array containing blogId, year, month or null if invalid
     */
    public function getPathDetails(string $path): ?array;

    /**
     * Normalize a given path.
     *
     * @param  string $path The path to normalize
     * @return string       The normalized path
     */
    public function normalizePath(string $path): string;

    /**
     * Create a cache identifier from path details.
     *
     * @param  array $details Array containing blogId, year, month
     * @return string         The cache identifier
     */
    public function createCacheIdentifier(array $details): string;

    /**
     * Heuristic to determine if a path looks like a file.
     *
     * @param  string $path The path to check
     * @return bool         True if it looks like a file, false otherwise
     */    
    public function looksLikeAFile(string $path): bool;
}