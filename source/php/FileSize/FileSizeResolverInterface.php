<?php

namespace S3_Local_Index\FileSize;

/**
 * Interface for resolving file sizes.
 */
interface FileSizeResolverInterface
{
    /**
     * Get the file size for a given path.
     *
     * @param string $path The file path
     * @return int|null The file size in bytes, or null if unable to determine
     */
    public function getFileSize(string $path): ?int;
}
