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
     * @inheritdoc
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
     * @inheritdoc
     */
    public function normalizePath(string $path): string
    {
        return ltrim(preg_replace('#^s3://#', '', $path), '/');
    }

    /**
     * @inheritdoc
     */
    public function createCacheIdentifier(array $details): string
    {
        return "index_{$details['blogId']}_{$details['year']}_{$details['month']}";
    }

    /**
     * @inheritdoc
     */
    public function looksLikeAFile(string $path): bool
    {
        // Ends with a slash → likely a directory
        if (substr($path, -1) === '/') {
            return false;
        }

        // Contains a dot after the last slash → likely a file with extension
        $basename = basename($path);
        if (strpos($basename, '.') !== false) {
            return true;
        }

        // Otherwise, we *guess* it's a directory
        return false;
    }
}