<?php

namespace S3_Local_Index\CLI;

use S3_Uploads\Plugin;
use S3_Local_Index\Stream\Reader;
use S3_Local_Index\Rebuild\RebuildTracker;
use WP_CLI;
use Exception;

class Command {

    /**
     * Creates the S3 index and stores it in the system temp directory.
     *
     * ## EXAMPLES
     *
     *     wp s3-index create
     *
     * @when after_wp_load
     */
    public function create($args = [], $assoc_args = []) {
        if (!class_exists(Plugin::class)) {
            WP_CLI::error('S3_Uploads plugin not loaded yet.');
            return;
        }

        $s3 = Plugin::get_instance()->s3();
        $bucket = Plugin::get_instance()->get_s3_bucket();

        WP_CLI::log("[S3 Local Index] Creating index for bucket: {$bucket}");

        // Clear cache before rebuilding index
        $cache = Reader::getCache();
        $cache->clear();
        WP_CLI::log("[S3 Local Index] Cache cleared.");

        $tempDir = sys_get_temp_dir() . '/s3-index-temp';
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
                        WP_CLI::log("[S3 Local Index] Indexed {$count} objects...");
                    }
                }
            }
        }

        foreach ($filesBySite as $blogId => $years) {
            foreach ($years as $year => $months) {
                foreach ($months as $month => $keys) {
                    $file = "{$tempDir}/s3-index-{$blogId}-{$year}-{$month}.json";
                    file_put_contents($file, json_encode($keys, JSON_PRETTY_PRINT));
                    WP_CLI::log("Written index for blog {$blogId} {$year}-{$month}, count: " . count($keys));
                }
            }
        }

        WP_CLI::success("Index created successfully. Total objects: {$count}");
        WP_CLI::log("[S3 Local Index] Cache will be populated on next access.");
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
            $rebuildList = RebuildTracker::getRebuildList();
            if (empty($rebuildList)) {
                WP_CLI::log("[S3 Local Index] No items in rebuild list.");
            } else {
                WP_CLI::log("[S3 Local Index] Current rebuild list:");
                foreach ($rebuildList as $item) {
                    WP_CLI::log("  - {$item}");
                }
            }
            return;
        }

        // Flush cache for the specific path
        $flushed = Reader::flushCacheForPath($path);
        if ($flushed) {
            WP_CLI::success("[S3 Local Index] Cache flushed for path: {$path}");
        } else {
            WP_CLI::warning("[S3 Local Index] Path does not match expected pattern: {$path}");
            return;
        }

        // Add to rebuild list if requested
        if ($addToRebuild) {
            $added = RebuildTracker::addPathToRebuildList($path);
            if ($added) {
                WP_CLI::log("[S3 Local Index] Added to rebuild list: {$path}");
            } else {
                WP_CLI::warning("[S3 Local Index] Failed to add to rebuild list: {$path}");
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
        if (!class_exists(Plugin::class)) {
            WP_CLI::error('S3_Uploads plugin not loaded yet.');
            return;
        }

        $clearList = isset($assoc_args['clear']);
        $rebuildAll = isset($assoc_args['all']);

        if ($rebuildAll) {
            WP_CLI::log("[S3 Local Index] Rebuilding all indexes...");
            $this->create($args, $assoc_args);
            if ($clearList) {
                RebuildTracker::clearRebuildList();
                WP_CLI::log("[S3 Local Index] Rebuild list cleared.");
            }
            return;
        }

        $rebuildList = RebuildTracker::getRebuildList();
        if (empty($rebuildList)) {
            WP_CLI::log("[S3 Local Index] No items in rebuild list.");
            return;
        }

        $s3 = Plugin::get_instance()->s3();
        $bucket = Plugin::get_instance()->get_s3_bucket();
        $tempDir = sys_get_temp_dir() . '/s3-index-temp';
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        WP_CLI::log("[S3 Local Index] Rebuilding " . count($rebuildList) . " specific indexes...");

        foreach ($rebuildList as $item) {
            $parts = explode('-', $item);
            if (count($parts) !== 3) {
                WP_CLI::warning("[S3 Local Index] Invalid rebuild item format: {$item}");
                continue;
            }

            [$blogId, $year, $month] = $parts;
            
            // Flush cache for this specific index
            $cacheKey = "index_{$blogId}_{$year}_{$month}";
            $cache = Reader::getCache();
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
                file_put_contents($file, json_encode($files, JSON_PRETTY_PRINT));
                
                WP_CLI::log("[S3 Local Index] Rebuilt index for blog {$blogId} {$year}-{$month}, count: {$count}");

                // Remove from rebuild list
                RebuildTracker::removeFromRebuildList($blogId, $year, $month);

            } catch (Exception $e) {
                WP_CLI::warning("[S3 Local Index] Failed to rebuild {$item}: " . $e->getMessage());
            }
        }

        if ($clearList) {
            RebuildTracker::clearRebuildList();
            WP_CLI::log("[S3 Local Index] Rebuild list cleared.");
        }

        WP_CLI::success("[S3 Local Index] Selective rebuild completed.");
    }
}