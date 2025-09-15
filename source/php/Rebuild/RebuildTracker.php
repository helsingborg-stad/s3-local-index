<?php

namespace S3_Local_Index\Rebuild;

/**
 * Tracks indexes that need to be rebuilt
 */
class RebuildTracker {
    
    private const REBUILD_LIST_FILE = 's3-index-rebuild-list.json';
    
    /**
     * Add an index to the rebuild list
     *
     * @param string $blog_id Blog ID
     * @param string $year Year
     * @param string $month Month
     * @return bool True on success, false on failure
     */
    public static function addToRebuildList(string $blog_id, string $year, string $month): bool {
        $rebuild_list = self::getRebuildList();
        $key = "{$blog_id}-{$year}-{$month}";
        
        if (!in_array($key, $rebuild_list, true)) {
            $rebuild_list[] = $key;
        }
        
        return self::saveRebuildList($rebuild_list);
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
     * @return array Array of rebuild keys in format "blog_id-year-month"
     */
    public static function getRebuildList(): array {
        $file = self::getRebuildListFile();
        if (!file_exists($file)) {
            return [];
        }
        
        $data = file_get_contents($file);
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
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }
    
    /**
     * Remove an item from the rebuild list
     *
     * @param string $blog_id Blog ID
     * @param string $year Year  
     * @param string $month Month
     * @return bool True on success, false on failure
     */
    public static function removeFromRebuildList(string $blog_id, string $year, string $month): bool {
        $rebuild_list = self::getRebuildList();
        $key = "{$blog_id}-{$year}-{$month}";
        
        $index = array_search($key, $rebuild_list, true);
        if ($index !== false) {
            unset($rebuild_list[$index]);
            $rebuild_list = array_values($rebuild_list); // Re-index array
        }
        
        return self::saveRebuildList($rebuild_list);
    }
    
    /**
     * Get the full path to the rebuild list file
     *
     * @return string Full path to rebuild list file
     */
    private static function getRebuildListFile(): string {
        return sys_get_temp_dir() . '/' . self::REBUILD_LIST_FILE;
    }
    
    /**
     * Save the rebuild list to file
     *
     * @param array $rebuild_list Array of rebuild keys
     * @return bool True on success, false on failure
     */
    private static function saveRebuildList(array $rebuild_list): bool {
        $file = self::getRebuildListFile();
        $data = json_encode($rebuild_list, JSON_PRETTY_PRINT);
        
        return file_put_contents($file, $data) !== false;
    }
}