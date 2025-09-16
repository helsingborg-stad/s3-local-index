<?php

namespace S3_Local_Index\FileSystem;

interface FileSystemInterface
{
    /**
     * Check if a file exists.
     *
     * @param  string $path File path
     * @return bool True if file exists, false otherwise
     */
    public function fileExists(string $path): bool;

    /**
     * Get file contents.
     *
     * @param  string $path File path
     * @return string|false File contents or false on failure
     */
    public function fileGetContents(string $path);

    /**
     * Put file contents.
     *
     * @param  string $path File path
     * @param  string $data Data to write
     * @return int|false Number of bytes written or false on failure
     */
    public function filePutContents(string $path, string $data);

    /**
     * Delete a file.
     *
     * @param  string $path File path
     * @return bool True on success, false on failure
     */
    public function unlink(string $path): bool;

    /**
     * Get temporary directory path.
     *
     * @return string Temporary directory path
     */
    public function getTempDir(): string;

    /**
     * Get cache directory path.
     *
     * @return string Cache directory path
     */
    public function getCacheDir(): string;
}