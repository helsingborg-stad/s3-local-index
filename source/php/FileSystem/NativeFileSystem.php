<?php

namespace S3_Local_Index\FileSystem;

use S3LocalIndex\Config\ConfigInterface;

/**
 * Native PHP file system implementation
 */
class NativeFileSystem implements FileSystemInterface
{
    /**
     * Constructor with optional config dependency for cache directory configuration
     *
     * @param ConfigInterface|null $config Configuration provider for cache directory
     */
    public function __construct(
        private ?ConfigInterface $config = null
    ) {
    }
    /**
     * Check if a file exists.
     *
     * @param  string $path File path
     * @return bool True if file exists, false otherwise
     */
    public function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Get file contents.
     *
     * @param  string $path File path
     * @return string|false File contents or false on failure
     */
    public function fileGetContents(string $path): string|false
    {
        return @file_get_contents($path);
    }

    /**
     * Put file contents.
     *
     * @param  string $path File path
     * @param  string $data Data to write
     * @return int|false Number of bytes written or false on failure
     */
    public function filePutContents(string $path, string $data)
    {
        return file_put_contents($path, $data);
    }

    /**
     * Delete a file.
     *
     * @param  string $path File path
     * @return bool True on success, false on failure
     */
    public function unlink(string $path): bool
    {
        return unlink($path);
    }

    /**
     * Get temporary directory path.
     *
     * @return string Temporary directory path
     */
    public function getTempDir(): string
    {
        return sys_get_temp_dir();
    }

    /**
     * Get cache directory path.
     *
     * @return string Cache directory path
     */
    public function getCacheDir(): string
    {
        if ($this->config !== null) {
            return $this->config->getCacheDirectory();
        }
        return sys_get_temp_dir();
    }

    /**
     * Generate cache file name based on index details.
     *
     * @param  array $details Array containing 'blogId', 'year', and 'month'
     * @return string Cache file path
     */
    public function getCacheFileName(array $details): string
    {
        $blogId = $details['blogId'];
        $year   = $details['year'];
        $month  = $details['month'];

        return "s3-index-{$blogId}-{$year}-{$month}.json";
    }

    /**
     * Get the full path to the cache file
     * 
     * @param  array $details Array containing 'blogId', 'year', and 'month'
     * @return string Full cache file path
     */
    public function getCacheFilePath(array $details) : string {
        $this->getCacheDir() . DIRECTORY_SEPARATOR . $this->getCacheFileName($details); 
    }
}
