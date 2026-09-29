<?php

namespace S3_Local_Index\FileSystem;

use S3_Local_Index\Config\ConfigInterface;

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

    public function mutateIndexFile(string $path, callable $mutation): array|false
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Cannot create index directory: {$directory}");
        }

        // Keep the lock on a separate inode: the index itself is replaced by rename().
        $lockPath = $path . '.lock';
        $lock = @fopen($lockPath, 'c');
        if ($lock === false && is_file($lockPath)) {
            // A CLI process may own the file. flock() also works on a readable
            // descriptor, so writers do not need permission to modify the lock.
            $lock = @fopen($lockPath, 'r');
        }
        if ($lock === false) {
            throw new \RuntimeException("Cannot open index lock: {$lockPath}");
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException("Cannot lock index: {$path}");
            }

            clearstatcache(true, $path);
            $current = [];
            if (is_file($path)) {
                $contents = file_get_contents($path);
                $current = $contents === false ? null : json_decode($contents, true);
                if (!is_array($current) || json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException("Cannot read valid index: {$path}");
                }
            }

            $next = $mutation($current);
            if ($next === false) {
                return false;
            }
            if (!is_array($next)) {
                throw new \RuntimeException("Index mutation returned invalid data: {$path}");
            }

            $encoded = json_encode($next, JSON_THROW_ON_ERROR);
            $temporary = tempnam($directory, '.s3-index-');
            if ($temporary === false) {
                throw new \RuntimeException("Cannot create temporary index: {$path}");
            }

            try {
                if (file_put_contents($temporary, $encoded) !== strlen($encoded)) {
                    throw new \RuntimeException("Cannot write complete index: {$path}");
                }
                // The CLI may run as root while PHP workers use another user.
                $existingMode = is_file($path) ? fileperms($path) : false;
                $mode = $existingMode === false ? 0644 : ($existingMode & 0777);
                if (!chmod($temporary, $mode) || !rename($temporary, $path)) {
                    throw new \RuntimeException("Cannot publish index: {$path}");
                }
                clearstatcache(true, $path);
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }

            return $next;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
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
    public function getCacheFilePath(array $details) : string
    {
        return $this->getCacheDir() . DIRECTORY_SEPARATOR . $this->getCacheFileName($details); 
    }
}
