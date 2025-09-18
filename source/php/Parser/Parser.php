<?php

namespace S3LocalIndex\Parser;

/**
 * Concrete implementation of ParserInterface for parsing S3 file paths.
 */
class Parser implements ParserInterface
{
    /**
     * Constructor.
     */
    public function __construct()
    {
    }

    /**
     * Extract index details from a file path.
     *
     * @param  string $path S3 file path
     * @return array|null   Array with blogId, year, month or null if path doesn't match pattern
     */
    public function getPathDetails(string $path): ?array
    {
        $path = ltrim($path, '/');
        if (preg_match('#(?:uploads/networks/\d+/sites/(\d+)/)?(?:uploads/)?(\d{4})/(\d{2})/#', $path, $m)) {
            return [
                'blogId' => $m[1] ?: '1',
                'year'   => $m[2] ?: '1970',
                'month'  => sprintf('%02d', (string) $m[3]) ?: '01',
            ];
        }
        return null;
    }

    /**
     * Normalize a path by removing protocol and leading slashes.
     *
     * @param  string $path Path to normalize
     * @return string       Normalized path
     */
    public function normalizePath(string $path): string
    {
        return ltrim(preg_replace('#^s3://#', '', $path), '/');
    }

    /**
     * Create a cache identifier from path details.
     *
     * @param  array $details Array containing blogId, year, month
     * @return string         Cache identifier string
     */
    public function createCacheIdentifier(array $details): string
    {
        return "index_{$details['blogId']}_{$details['year']}_{$details['month']}";
    }
}