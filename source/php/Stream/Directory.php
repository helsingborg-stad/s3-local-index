<?php

namespace S3_Local_Index\Stream;

/**
 * Directory stream handler for S3 with local index support.
 * 
 * This class provides directory listing functionality for S3 paths
 * using a local index to quickly enumerate files without making
 * expensive S3 API calls.
 */
class Directory
{

    private array $dirKeys = [];
    private int $dirPosition = 0;
    private array $index = [];

    /**
     * Constructor with dependency injection
     *
     * @param Reader $reader Reader instance for loading index data
     */
    public function __construct(
        private Reader $reader
    ) {
    }

    /**
     * Open a directory for reading.
     * 
     * Implementation of PHP's dir_opendir for the stream wrapper.
     * Loads the index and prepares file list for the given directory path.
     * 
     * @param  string $path    Directory path to open
     * @param  int    $options Stream options (unused)
     * @return bool True if directory opened successfully
     */
    public function dir_opendir(string $path, int $options): bool
    {
        $this->index = $this->reader->loadIndex($path);
        $this->dirKeys = [];

        $prefix = rtrim(str_replace('s3://', '', $path), '/') . '/';
        foreach ($this->index as $key => $_) {
            if (str_starts_with($key, $prefix)) {
                $this->dirKeys[] = substr($key, strlen($prefix));
            }
        }

        $this->dirPosition = 0;
        return true;
    }

    /**
     * Read next entry from directory.
     * 
     * Implementation of PHP's dir_readdir for the stream wrapper.
     * Returns the next filename in the directory or false when done.
     * 
     * @return string|false Next filename or false if no more entries
     */
    public function dir_readdir(): false|string
    {
        if ($this->dirPosition < count($this->dirKeys)) {
            return $this->dirKeys[$this->dirPosition++];
        }
        return false;
    }

    /**
     * Close the directory handle.
     * 
     * Implementation of PHP's dir_closedir for the stream wrapper.
     * Cleans up the directory state.
     * 
     * @return void
     */
    public function dir_closedir(): void
    {
        $this->dirKeys = [];
    }
}