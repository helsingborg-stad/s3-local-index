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
        echo "Parsing path: $path\n"; // Debug line to trace path parsing

        /*
        * Supported path patterns:
        *
        * Path Example                                   | networkId | blogId | year  | month  |
        * ---------------------------------------------- | ----------|--------|-------|--------|
        * uploads/2023/10/file.jpg                       | null      | 1      | 2023  | 10     |
        * /uploads/2023/10/file.jpg                      | null      | 1      | 2023  | 10     |
        * uploads/networks/2/sites/3/2023/10/file.jpg    | 2         | 3      | 2023  | 10     |
        * /uploads/networks/2/sites/3/2023/10/file.jpg   | 2         | 3      | 2023  | 10     |
        * uploads/networks/1/2025/10                     | 1         | 1      | 2025  | 10     |
        * helsingborg-se/uploads/networks/1/2025/10      | 1         | 1      | 2025  | 10     |
        * uploads/networks/9/2022/11/                    | 9         | 1      | 2022  | 11     |
        */

        $path = ltrim($path, '/');
        if (preg_match('#/?uploads/(?:networks/(\d+)(?:/sites/(\d+))?/)?(\d{4})/(\d{2})(?:/|$)#', $path, $m)) {
            return [
                'networkId' => $m[1] ?: '1',
                'blogId'    => $m[2] ?: '1',
                'year'      => $m[3] ?: '1970',
                'month'     => sprintf('%02d', (string) ($m[4] ?? '1')),
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