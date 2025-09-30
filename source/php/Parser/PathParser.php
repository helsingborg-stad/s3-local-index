<?php

namespace S3_Local_Index\Parser;

/**
 * Concrete implementation of PathParserInterface for parsing S3 file paths.
 */
class PathParser implements PathParserInterface
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
     * Normalize a given path and ensure it has the s3:// protocol prefix.
     *
     * @param  string $path The path to normalize
     * @return string       The normalized path with s3:// prefix
     */
    public function normalizePathWithProtocol(string $path): string
    {
        return 's3://' . $this->normalizePath($path);
    }
}