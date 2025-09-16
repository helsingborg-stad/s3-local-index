<?php

namespace S3_Local_Index\Stream;

/**
 * Interface for directory stream handlers.
 * 
 * Defines the contract for directory operations in stream wrappers.
 */
interface DirectoryInterface
{
    /**
     * Open a directory for reading.
     *
     * @param string $path Directory path to open
     * @param int $options Stream options
     * @return bool True if directory opened successfully
     */
    public function dir_opendir(string $path, int $options): bool;

    /**
     * Read next entry from directory.
     *
     * @return string|false Next filename or false if no more entries
     */
    public function dir_readdir(): false|string;

    /**
     * Close the directory handle.
     *
     * @return void
     */
    public function dir_closedir(): void;
}