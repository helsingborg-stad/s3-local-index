<?php

namespace S3_Local_Index\Integration;

use WpService\Contracts\AddAction;

/**
 * WordPress integration helper for cache flushing
 * This class can be used to integrate cache flushing with WordPress file operations
 */
class WordPressIntegration
{
    
    /**
     * Constructor.
     *
     * @param AddAction                              $wpService      WordPress service for adding actions
     * @param \S3_Local_Index\Stream\Reader          $reader         Reader instance for cache operations
     * @param \S3_Local_Index\Rebuild\RebuildTracker $rebuildTracker Rebuild tracker instance
     */
    public function __construct(
        private AddAction $wpService,
        private \S3_Local_Index\Stream\Reader $reader,
        private \S3_Local_Index\Rebuild\RebuildTracker $rebuildTracker
    ) {
    }
    
    /**
     * Handle file upload - flush cache and optionally add to rebuild list
     *
     * @param  string $filePath     The uploaded file path
     * @param  bool   $addToRebuild Whether to add to rebuild list
     * @return bool True if cache was flushed
     */
    public function onFileUpload(string $filePath, bool $addToRebuild = false): bool
    {
        $flushed = $this->reader->flushCacheForPath($filePath);
        
        if ($flushed && $addToRebuild) {
            $this->rebuildTracker->addPathToRebuildList($filePath);
        }
        
        return $flushed;
    }
    
    /**
     * Handle file deletion - flush cache and add to rebuild list
     *
     * @param  string $filePath The deleted file path
     * @return bool True if cache was flushed
     */
    public function onFileDelete(string $filePath): bool
    {
        $flushed = $this->reader->flushCacheForPath($filePath);
        
        if ($flushed) {
            $this->rebuildTracker->addPathToRebuildList($filePath);
        }
        
        return $flushed;
    }
    
    /**
     * Initialize WordPress hooks for automatic cache flushing
     * Call this during plugin initialization to enable automatic cache management
     */
    public function initHooks(): void
    {
        // Note: These are example hooks - actual implementation would depend on 
        // the specific WordPress file handling system being used
        
        // Example hook for file uploads
        $this->wpService->addAction(
            'wp_handle_upload', function ($upload) {
                if (isset($upload['url'])) {
                    // Extract S3 path from upload URL
                    $s3Path = $this->extractS3PathFromUrl($upload['url']);
                    if ($s3Path) {
                        $this->onFileUpload($s3Path, true);
                    }
                }
            }
        );
        
        // Example hook for file deletions
        $this->wpService->addAction(
            'delete_attachment', function ($attachment_id) {
                $fileUrl = wp_get_attachment_url($attachment_id);
                if ($fileUrl) {
                    $s3Path = $this->extractS3PathFromUrl($fileUrl);
                    if ($s3Path) {
                        $this->onFileDelete($s3Path);
                    }
                }
            }
        );
    }
    
    /**
     * Extract S3 path from a URL
     * This is a simplified example - actual implementation would depend on S3 URL structure
     *
     * @param  string $url File URL
     * @return string|null S3 path or null if not an S3 URL
     */
    private function extractS3PathFromUrl(string $url): ?string
    {
        // This is a simplified example
        // Real implementation would parse the actual S3 URL format used by the site
        if (strpos($url, 'uploads/') !== false) {
            $pathStart = strpos($url, 'uploads/');
            return substr($url, $pathStart);
        }
        
        return null;
    }
}