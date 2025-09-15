<?php

namespace S3_Local_Index\CLI;
use S3_Uploads\Plugin;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\FileSystem\NativeFileSystem;
use WP_CLI;
use Exception;
use WpService\WpService;

class Command {

  public function __construct(
    private WpService $wpService, 
    private Plugin $s3, 
    private WP_CLI $cli,
    private ?FileSystemInterface $fileSystem = null,
    private ?\S3_Local_Index\Rebuild\RebuildTracker $rebuildTracker = null
  ) {
    $this->fileSystem ??= new NativeFileSystem();
    $this->rebuildTracker ??= new \S3_Local_Index\Rebuild\RebuildTracker($this->fileSystem);
  }

    public function create($args = [], $assoc_args = []) {

        $s3     = $this->s3::get_instance()->s3();
        $bucket = $this->s3::get_instance()->get_s3_bucket();

        $this->cli::log("[S3 Local Index] Creating index for bucket: {$bucket}");

        // Clear cache before rebuilding index
        $cache = \S3_Local_Index\Cache\CacheFactory::createDefault($this->wpService);
        $cache->clear();
        $this->cli::log("[S3 Local Index] Cache cleared.");

        $tempDir = $this->fileSystem->getTempDir() . '/s3-index-temp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $filesBySite = [];
        $count = 0;
        $paginator = $s3->getPaginator('ListObjectsV2', ['Bucket' => $bucket]);

        foreach ($paginator as $page) {
            if (!empty($page['Contents'])) {
                foreach ($page['Contents'] as $obj) {
                    $key = $obj['Key'];
                    if (preg_match('#uploads/networks/\d+/sites/(\d+)/(\d{4})/(\d{2})/#', $key, $m)) {
                        $blogId = $m[1];
                        $year = $m[2];
                        $month = $m[3];
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
                    $file = "{$tempDir}/s3-index-{$blogId}-{$year}-{$month}.json";
                    $this->fileSystem->filePutContents($file, json_encode($keys, JSON_PRETTY_PRINT));
                    $this->cli::log("Written index for blog {$blogId} {$year}-{$month}, count: " . count($keys));
                }
            }
        }

        $this->cli::success("Index created successfully. Total objects: {$count}");
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
    public function flush($args = [], $assoc_args = []) {
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
        $cache = \S3_Local_Index\Cache\CacheFactory::createDefault($this->wpService);
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
    public function rebuild($args = [], $assoc_args = []) {

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

        $s3 = $this->s3::get_instance()->s3();
        $bucket = $this->s3::get_instance()->get_s3_bucket();
        $tempDir = $this->fileSystem->getTempDir() . '/s3-index-temp';
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
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
            $cache = \S3_Local_Index\Cache\CacheFactory::createDefault($this->wpService);
            $cache->delete($cacheKey);

            // Rebuild this specific index
            $prefix = $blogId === '1' 
                ? "uploads/{$year}/{$month}/" 
                : "uploads/networks/*/sites/{$blogId}/{$year}/{$month}/";

            $files = [];
            $count = 0;

            try {
                $paginator = $s3->getPaginator('ListObjectsV2', [
                    'Bucket' => $bucket,
                    'Prefix' => str_replace('*', '', $prefix) // Remove wildcard for actual query
                ]);

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
                $file = "{$tempDir}/s3-index-{$blogId}-{$year}-{$month}.json";
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