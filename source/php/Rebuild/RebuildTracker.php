<?php

namespace S3_Local_Index\Rebuild;

use S3_Local_Index\FileSystem\FileSystemInterface;
use S3LocalIndex\Parser\ParserInterface;

/**
 * Tracks indexes that need to be rebuilt
 */
class RebuildTracker implements RebuildTrackerInterface
{
    
    private const REBUILD_LIST_FILE = 's3-index-rebuild-list.json';

    /**
     * Constructor with dependency injection
     *
     * @param FileSystemInterface $fileSystem
     * @param ParserInterface     $parser
     */
    public function __construct(
        private FileSystemInterface $fileSystem,
        private ParserInterface $parser
    ) {
    }
    
    /**
     * Add an index to the rebuild list
     *
     * @param  string $blogId Blog ID
     * @param  string $year   Year
     * @param  string $month  Month
     * @return bool True on success, false on failure
     */
    public function addToRebuildList(string $blogId, string $year, string $month): bool
    {
        $rebuildList = $this->getRebuildList();
        $key = "{$blogId}-{$year}-{$month}";
        
        if (!in_array($key, $rebuildList, true)) {
            $rebuildList[] = $key;
        }
        
        return $this->saveRebuildList($rebuildList);
    }
    
    /**
     * Add index for a file path to the rebuild list
     *
     * @param  string $path S3 file path
     * @return bool True if added to rebuild list, false if path doesn't match pattern
     */
    public function addPathToRebuildList(string $path): bool
    {
        $details = $this->parser->getPathDetails($path);
        if ($details === null) {
            return false;
        }

        $blogId = (string) $details['blogId'];
        $year = (string) $details['year'];
        $month = sprintf('%02d', $details['month']); // Ensure leading zero format

        return $this->addToRebuildList($blogId, $year, $month);
    }
    
    /**
     * Get the current rebuild list
     *
     * @return array Array of rebuild keys in format "blogId-year-month"
     */
    public function getRebuildList(): array
    {
        $file = $this->getRebuildListFile();
        
        if (!$this->fileSystem->fileExists($file)) {
            return [];
        }
        
        $data = $this->fileSystem->fileGetContents($file);
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
    public function clearRebuildList(): bool
    {
        $file = $this->getRebuildListFile();
        
        if ($this->fileSystem->fileExists($file)) {
            return $this->fileSystem->unlink($file);
        }
        return true;
    }
    
    /**
     * Remove an item from the rebuild list
     *
     * @param  string $blogId Blog ID
     * @param  string $year   Year  
     * @param  string $month  Month
     * @return bool True on success, false on failure
     */
    public function removeFromRebuildList(string $blogId, string $year, string $month): bool
    {
        $rebuildList = $this->getRebuildList();
        $key = "{$blogId}-{$year}-{$month}";
        
        $index = array_search($key, $rebuildList, true);
        if ($index !== false) {
            unset($rebuildList[$index]);
            $rebuildList = array_values($rebuildList); // Re-index array
        }
        
        return $this->saveRebuildList($rebuildList);
    }
    
    /**
     * Get the full path to the rebuild list file
     *
     * @return string Full path to rebuild list file
     */
    private function getRebuildListFile(): string
    {
        return $this->fileSystem->getCacheDir() . '/' . self::REBUILD_LIST_FILE;
    }
    
    /**
     * Save the rebuild list to file
     *
     * @param  array $rebuildList Array of rebuild keys
     * @return bool True on success, false on failure
     */
    private function saveRebuildList(array $rebuildList): bool
    {
        $file = $this->getRebuildListFile();
        $data = json_encode($rebuildList, JSON_PRETTY_PRINT);
        
        return $this->fileSystem->filePutContents($file, $data) !== false;
    }
}