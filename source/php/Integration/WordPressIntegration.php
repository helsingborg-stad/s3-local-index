<?php

namespace S3_Local_Index\Integration;

use S3_Local_Index\Stream\Reader;
use S3_Local_Index\Rebuild\RebuildTracker;

/**
 * WordPress integration helper for cache flushing
 * This class can be used to integrate cache flushing with WordPress file operations
 */
class WordPressIntegration {
    
    /**
     * Handle file upload - flush cache and optionally add to rebuild list
     *
     * @param string $filePath The uploaded file path
     * @param bool $addToRebuild Whether to add to rebuild list
     * @return bool True if cache was flushed
     */
    public static function onFileUpload(string $filePath, bool $addToRebuild = false): bool {
        $flushed = Reader::flushCacheForPath($filePath);
        
        if ($flushed && $addToRebuild) {
            RebuildTracker::addPathToRebuildList($filePath);
        }
        
        return $flushed;
    }
    
    /**
     * Handle file deletion - flush cache and add to rebuild list
     *
     * @param string $filePath The deleted file path
     * @return bool True if cache was flushed
     */
    public static function onFileDelete(string $filePath): bool {
        $flushed = Reader::flushCacheForPath($filePath);
        
        if ($flushed) {
            RebuildTracker::addPathToRebuildList($filePath);
        }
        
        return $flushed;
    }
    
    /**
     * Initialize WordPress hooks for automatic cache flushing
     * Call this during plugin initialization to enable automatic cache management
     */
    public static function initHooks(): void {
        // Note: These are example hooks - actual implementation would depend on 
        // the specific WordPress file handling system being used
        
        // Example hook for file uploads
        add_action('wp_handle_upload', function($upload) {
            if (isset($upload['url'])) {
                // Extract S3 path from upload URL
                $s3Path = self::extractS3PathFromUrl($upload['url']);
                if ($s3Path) {
                    self::onFileUpload($s3Path, true);
                }
            }
        });
        
        // Example hook for file deletions
        add_action('delete_attachment', function($attachment_id) {
            $fileUrl = wp_get_attachment_url($attachment_id);
            if ($fileUrl) {
                $s3Path = self::extractS3PathFromUrl($fileUrl);
                if ($s3Path) {
                    self::onFileDelete($s3Path);
                }
            }
        });
    }
    
    /**
     * Extract S3 path from a URL
     * This is a simplified example - actual implementation would depend on S3 URL structure
     *
     * @param string $url File URL
     * @return string|null S3 path or null if not an S3 URL
     */
    private static function extractS3PathFromUrl(string $url): ?string {
        // This is a simplified example
        // Real implementation would parse the actual S3 URL format used by the site
        if (strpos($url, 'uploads/') !== false) {
            $pathStart = strpos($url, 'uploads/');
            return substr($url, $pathStart);
        }
        
        return null;
    }
}