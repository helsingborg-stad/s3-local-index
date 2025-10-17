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
        * /uploads/networks/2/sites/3/2023/01/file.jpg   | 2         | 3      | 2023  | 1     |
        */

        $path = ltrim($path, '/');

        // Default values
        $networkId  = '1';
        $blogId     = '1';
        $year       = null;
        $month      = null;

        // Extract networkId and blogId
        if (preg_match('#uploads/networks/(\d+)(?:/sites/(\d+))?#', $path, $m)) {
            $networkId  = $m[1] ?? '1';
            $blogId     = isset($m[2]) && $m[2] !== '' ? $m[2] : '1';
        } elseif (preg_match('#uploads/sites/(\d+)#', $path, $m)) {
            $blogId     = $m[1] ?? '1';
            $networkId  = '1';
        }

        // Extract year and month together from /YYYY/MM/ segment
        if (preg_match('#/(\d{4})/(\d{2})(?:/|$)#', $path, $m)) {
            $year  = $m[1] ?: $year;
            $month = $m[2] ?: $month;
        }

        if(is_null($month) || is_null($year)) {
            return null;
        }

        return [
            'networkId' => $networkId,
            'blogId'    => $blogId,
            'year'      => $year,
            'month'     => str_pad($month, 2, '0', STR_PAD_LEFT),
        ];
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