<?php

namespace S3_Local_Index\Parser;

/**
 * Interface defining path parsing operations for S3 file paths.
 */
interface PathParserInterface
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
     * Normalize a given path and ensure it has the s3:// protocol prefix.
     *
     * @param  string $path The path to normalize
     * @return string       The normalized path with s3:// prefix
     */
    public function normalizePathWithProtocol(string $path): string;
}