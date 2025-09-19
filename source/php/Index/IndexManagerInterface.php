<?php

namespace S3_Local_Index\Stream;

/**
 * Defines operations for managing a filesystem index.
 */
interface IndexManagerInterface
{
    /**
     * Load index data for a given path.
     *
     * @param string $path
     * @return array Array width a index if found. Otherwise null.
     */
    public function get(string $path): ?array;

    /**
     * Update the local index with a new file path.
     *
     * @param string $path
     * @return bool True if the path was updated, or added to the string. 
     */
    public function update(string $path): bool;

    /**
     * Remove a file path from the local index.
     *
     * @param string $path
     * @return bool True if the path was deleted from the interface, otherwise false.
     */
    public function delete(string $path): bool;
}