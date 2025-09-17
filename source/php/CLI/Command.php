<?php

namespace S3_Local_Index\CLI;

use S3_Uploads\Plugin;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\Cache\CacheFactory;
use WP_CLI;
use Exception;
use S3_Local_Index\Rebuild\RebuildTrackerInterface;
use WpService\WpService;

/**
 * WP-CLI command handler for S3 Local Index operations.
 * 
 * This class provides CLI commands for managing S3 file indexes, including
 * creating full indexes, flushing specific path caches, and rebuilding
 * selective indexes from a rebuild queue.
 */
class Command
{

    /**
     * Constructor for CLI Command.
     *
     * @param WpService                                   $wpService      The WordPress service provider
     * @param Plugin                                      $s3             The S3 Uploads plugin instance
     * @param WP_CLI                                      $cli            The WP-CLI interface
     * @param FileSystemInterface|null                    $fileSystem     File system handler (optional, defaults to NativeFileSystem)
     * @param RebuildTrackerInterface|null                $rebuildTracker Rebuild tracking service (optional)
     * @param CacheFactory|null                           $cacheFactory   Cache factory service (optional)
     */
    public function __construct(
        private WpService $wpService, 
        private Plugin $s3, 
        private $cli,
        private FileSystemInterface $fileSystem,
        private RebuildTrackerInterface $rebuildTracker,
        private CacheFactory $cacheFactory
    ) {
    }

    /**
     * Create a complete S3 index by scanning all objects in the bucket.
     * 
     * This command iterates through all objects in the S3 bucket and creates
     * local index files organized by blog ID, year, and month. It clears the
     * cache before starting and provides progress updates during execution.
     * 
     * ## OPTIONS
     * 
     * No specific options required.
     * 
     * ## EXAMPLES
     * 
     *     wp s3-index create
     * 
     * @param  array $args       Positional arguments (unused)
     * @param  array $assoc_args Associative arguments (unused)
     * @return void
     * 
     * @when after_wp_load
     */
    public function create($args = [], $assoc_args = [])
    {

        $s3     = $this->s3->s3();
        $bucket = $this->s3->get_s3_bucket();

        $this->cli::log("[S3 Local Index] Creating index for bucket: {$bucket}");

        // Clear cache before rebuilding index
        $cache = $this->cacheFactory->createDefault();
        $cache->clear();
        $this->cli::log("[S3 Local Index] Cache cleared.");

        $cacheDir = $this->fileSystem->getCacheDir();
        if (!is_dir($cacheDir)) {
            $status = mkdir($cacheDir, 0777, true);

            if(!$status) {
                $this->cli::error("[S3 Local Index] Failed to create cache directory: {$cacheDir}");
                return;
            }
        }

        $this->cli::log("[S3 Local Index] Using cache directory: {$cacheDir}");

        $filesBySite = [];
        $count = 0;
        $paginator = $s3->getPaginator('ListObjectsV2', ['Bucket' => $bucket]);

        foreach ($paginator as $page) {
            if (!empty($page['Contents'])) {
                foreach ($page['Contents'] as $obj) {
                    $key = $obj['Key'];

                    if (preg_match('#(?:uploads/networks/\d+/sites/(\d+)/)?(?:uploads/)?(\d{4})/(\d{2})/#', $key, $m)) {
                        $locationDetails = [
                            'blogId' => $m[1] ?? '1', // if multisite captured, use it; otherwise default to 1
                            'year'   => $m[2],
                            'month'  => $m[3],
                        ];
                    }
                    
                    if (!empty($locationDetails)) {
                        extract($locationDetails);
                        $filesBySite[$blogId][$year][$month][] = $key;
                    }

                    $count++;
                    if ($count % 1000 === 0) {
                        $this->cli::log("[S3 Local Index] Indexed {$count} objects...");
                    }
                }
            }
        }

        foreach ($filesBySite as $blogId => $years) {
            foreach ($years as $year => $months) {
                foreach ($months as $month => $keys) {
                    $file = "{$cacheDir}/s3-index-{$blogId}-{$year}-{$month}.json";
                    $this->fileSystem->filePutContents($file, json_encode($keys, JSON_PRETTY_PRINT));
                    $this->cli::log("Written index for blog {$blogId} {$year}-{$month}. [File: {$file}] [Items: " . count($keys) . "]");
                }
            }
        }

        $this->cli::success("[S3 Local Index] Index created successfully. Total objects: {$count}");
        $this->cli::log("[S3 Local Index] Cache will be populated on next access.");
    }

    /**
     * Flush cache for a specific file path or rebuild list item.
     *
     * ## OPTIONS
     *
     * [<path>]
     * : S3 file path to flush cache for. If not provided, shows rebuild list.
     *
     * [--add]
     * : Add the path to rebuild list instead of just flushing cache.
     *
     * ## EXAMPLES
     *
     *     wp s3-index flush uploads/2023/01/file.jpg
     *     wp s3-index flush uploads/2023/01/file.jpg --add
     *     wp s3-index flush
     *
     * @when after_wp_load
     */
    public function flush($args = [], $assoc_args = [])
    {
        $path = $args[0] ?? null;
        $addToRebuild = isset($assoc_args['add']);

        if ($path === null) {
            // Show current rebuild list
            $rebuildList = $this->rebuildTracker->getRebuildList();
            if (empty($rebuildList)) {
                $this->cli::log("[S3 Local Index] No items in rebuild list.");
            } else {
                $this->cli::log("[S3 Local Index] Current rebuild list:");
                foreach ($rebuildList as $item) {
                    $this->cli::log("  - {$item}");
                }
            }
            return;
        }

        // Create reader instance to flush cache
        $cache = $this->cacheFactory->createDefault();
        $reader = new \S3_Local_Index\Stream\Reader($cache, $this->fileSystem);
        
        // Flush cache for the specific path
        $flushed = $reader->flushCacheForPath($path);
        if ($flushed) {
            $this->cli::success("[S3 Local Index] Cache flushed for path: {$path}");
        } else {
            $this->cli::warning("[S3 Local Index] Path does not match expected pattern: {$path}");
            return;
        }

        // Add to rebuild list if requested
        if ($addToRebuild) {
            $added = $this->rebuildTracker->addPathToRebuildList($path);
            if ($added) {
                $this->cli::log("[S3 Local Index] Added to rebuild list: {$path}");
            } else {
                $this->cli::warning("[S3 Local Index] Failed to add to rebuild list: {$path}");
            }
        }
    }

    /**
     * Rebuild specific indexes from the rebuild list.
     *
     * ## OPTIONS
     *
     * [--clear]
     * : Clear the rebuild list after rebuilding.
     *
     * [--all]
     * : Rebuild all indexes (same as create command).
     *
     * ## EXAMPLES
     *
     *     wp s3-index rebuild
     *     wp s3-index rebuild --clear
     *     wp s3-index rebuild --all
     *
     * @when after_wp_load
     */
    public function rebuild($args = [], $assoc_args = [])
    {

        $clearList = isset($assoc_args['clear']);
        $rebuildAll = isset($assoc_args['all']);

        if ($rebuildAll) {
            $this->cli::log("[S3 Local Index] Rebuilding all indexes...");
            $this->create($args, $assoc_args);
            if ($clearList) {
                $this->rebuildTracker->clearRebuildList();
                $this->cli::log("[S3 Local Index] Rebuild list cleared.");
            }
            return;
        }

        $rebuildList = $this->rebuildTracker->getRebuildList();
        if (empty($rebuildList)) {
            $this->cli::log("[S3 Local Index] No items in rebuild list.");
            return;
        }

        $s3 = $this->s3->s3();
        $bucket = $this->s3->get_s3_bucket();
        $cacheDir = $this->fileSystem->getCacheDir();
        
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        $this->cli::log("[S3 Local Index] Rebuilding " . count($rebuildList) . " specific indexes...");

        foreach ($rebuildList as $item) {
            $parts = explode('-', $item);
            if (count($parts) !== 3) {
                $this->cli::warning("[S3 Local Index] Invalid rebuild item format: {$item}");
                continue;
            }

            [$blogId, $year, $month] = $parts;
            
            // Flush cache for this specific index
            $cacheKey = "index_{$blogId}_{$year}_{$month}";
            $cache = $this->cacheFactory->createDefault();
            $cache->delete($cacheKey);

            // Rebuild this specific index
            $prefix = $blogId === '1' 
                ? "uploads/{$year}/{$month}/" 
                : "uploads/networks/*/sites/{$blogId}/{$year}/{$month}/";

            $files = [];
            $count = 0;

            try {
                $paginator = $s3->getPaginator(
                    'ListObjectsV2', [
                    'Bucket' => $bucket,
                    'Prefix' => str_replace('*', '', $prefix) // Remove wildcard for actual query
                    ]
                );

                foreach ($paginator as $page) {
                    if (!empty($page['Contents'])) {
                        foreach ($page['Contents'] as $obj) {
                            $key = $obj['Key'];
                            // Match the specific pattern for this blog/year/month
                            $pattern = $blogId === '1' 
                                ? "#^uploads/{$year}/{$month}/#" 
                                : "#^uploads/networks/\d+/sites/{$blogId}/{$year}/{$month}/#";
                            
                            if (preg_match($pattern, $key)) {
                                $files[] = $key;
                                $count++;
                            }
                        }
                    }
                }

                // Write the index file
                $file = "{$cacheDir}/s3-index-{$blogId}-{$year}-{$month}.json";
                $this->fileSystem->filePutContents($file, json_encode($files, JSON_PRETTY_PRINT));
                
                $this->cli::log("[S3 Local Index] Rebuilt index for blog {$blogId} {$year}-{$month}, count: {$count}");

                // Remove from rebuild list
                $this->rebuildTracker->removeFromRebuildList($blogId, $year, $month);

            } catch (Exception $e) {
                $this->cli::warning("[S3 Local Index] Failed to rebuild {$item}: " . $e->getMessage());
            }
        }

        if ($clearList) {
            $this->rebuildTracker->clearRebuildList();
            $this->cli::log("[S3 Local Index] Rebuild list cleared.");
        }

        $this->cli::success("[S3 Local Index] Selective rebuild completed.");
    }
}