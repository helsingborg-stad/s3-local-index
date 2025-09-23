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
}