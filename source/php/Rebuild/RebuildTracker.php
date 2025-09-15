<?php

namespace S3_Local_Index\Rebuild;

use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\FileSystem\NativeFileSystem;

/**
 * Tracks indexes that need to be rebuilt
 */
class RebuildTracker {
    
    private const REBUILD_LIST_FILE = 's3-index-rebuild-list.json';
    private static ?FileSystemInterface $fileSystem = null;

    /**
     * Set the file system implementation.
     *
     * @param FileSystemInterface $fileSystem
     */
    public static function setFileSystem(FileSystemInterface $fileSystem): void {
        self::$fileSystem = $fileSystem;
    }

    /**
     * Get the file system implementation.
     *
     * @return FileSystemInterface
     */
    private static function getFileSystem(): FileSystemInterface {
        if (self::$fileSystem === null) {
            self::$fileSystem = new NativeFileSystem();
        }
        return self::$fileSystem;
    }
    
    /**
     * Add an index to the rebuild list
     *
     * @param string $blogId Blog ID
     * @param string $year Year
     * @param string $month Month
     * @return bool True on success, false on failure
     */
    public static function addToRebuildList(string $blogId, string $year, string $month): bool {
        $rebuildList = self::getRebuildList();
        $key = "{$blogId}-{$year}-{$month}";
        
        if (!in_array($key, $rebuildList, true)) {
            $rebuildList[] = $key;
        }
        
        return self::saveRebuildList($rebuildList);
    }
    
    /**
     * Add index for a file path to the rebuild list
     *
     * @param string $path S3 file path
     * @return bool True if added to rebuild list, false if path doesn't match pattern
     */
    public static function addPathToRebuildList(string $path): bool {
        $path = ltrim($path, '/');
        
        // Try multisite pattern first
        if (preg_match('#uploads/networks/\d+/sites/(\d+)/(\d{4})/(\d{2})/#', $path, $m)) {
            return self::addToRebuildList($m[1], $m[2], $m[3]);
        }
        
        // Try single site pattern
        if (preg_match('#uploads/(\d{4})/(\d{2})/#', $path, $m)) {
            return self::addToRebuildList('1', $m[1], $m[2]);
        }
        
        return false;
    }
    
    /**
     * Get the current rebuild list
     *
     * @return array Array of rebuild keys in format "blogId-year-month"
     */
    public static function getRebuildList(): array {
        $file = self::getRebuildListFile();
        $fileSystem = self::getFileSystem();
        
        if (!$fileSystem->fileExists($file)) {
            return [];
        }
        
        $data = $fileSystem->fileGetContents($file);
        if ($data === false) {
            return [];
        }
        
        $list = json_decode($data, true);
        return is_array($list) ? $list : [];
    }
    
    /**
     * Clear the rebuild list
     *
     * @return bool True on success, false on failure
     */
    public static function clearRebuildList(): bool {
        $file = self::getRebuildListFile();
        $fileSystem = self::getFileSystem();
        
        if ($fileSystem->fileExists($file)) {
            return $fileSystem->unlink($file);
        }
        return true;
    }
    
    /**
     * Remove an item from the rebuild list
     *
     * @param string $blogId Blog ID
     * @param string $year Year  
     * @param string $month Month
     * @return bool True on success, false on failure
     */
    public static function removeFromRebuildList(string $blogId, string $year, string $month): bool {
        $rebuildList = self::getRebuildList();
        $key = "{$blogId}-{$year}-{$month}";
        
        $index = array_search($key, $rebuildList, true);
        if ($index !== false) {
            unset($rebuildList[$index]);
            $rebuildList = array_values($rebuildList); // Re-index array
        }
        
        return self::saveRebuildList($rebuildList);
    }
    
    /**
     * Get the full path to the rebuild list file
     *
     * @return string Full path to rebuild list file
     */
    private static function getRebuildListFile(): string {
        $fileSystem = self::getFileSystem();
        return $fileSystem->getTempDir() . '/' . self::REBUILD_LIST_FILE;
    }
    
    /**
     * Save the rebuild list to file
     *
     * @param array $rebuildList Array of rebuild keys
     * @return bool True on success, false on failure
     */
    private static function saveRebuildList(array $rebuildList): bool {
        $file = self::getRebuildListFile();
        $data = json_encode($rebuildList, JSON_PRETTY_PRINT);
        $fileSystem = self::getFileSystem();
        
        return $fileSystem->filePutContents($file, $data) !== false;
    }
}