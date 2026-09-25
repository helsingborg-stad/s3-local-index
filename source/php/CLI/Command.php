<?php

namespace S3_Local_Index\CLI;

use S3_Uploads\Plugin;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\Cache\CacheInterface;
use WP_CLI;
use WpService\WpService;
use S3_Local_Index\Parser\PathParserInterface;
use S3_Local_Index\Logger\LoggerInterface;

/**
 * WP-CLI command handler for S3 Local Index operations.
 * 
 * This class provides CLI commands for creating s3 index.
 */
class Command
{

    /**
     * Constructor for CLI Command.
     *
     * @param WpService                $wpService  The WordPress service provider
     * @param Plugin                   $s3         The S3 Uploads plugin instance
     * @param WP_CLI                   $cli        The WP-CLI interface
     * @param FileSystemInterface|null $fileSystem File system handler (optional, defaults to NativeFileSystem)
     * @param CacheInterface|null      $cache      Cache service (optional)
     * @param PathParserInterface|null $pathParser Parser for path operations (optional)
     */
    public function __construct(
        private WpService $wpService, 
        private Plugin $s3, 
        private $cli,
        private FileSystemInterface $fileSystem,
        private CacheInterface $cache,
        private PathParserInterface $pathParser
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
        $this->cache->clear();
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

        $scanStartedAt = time();
        $filesBySite = [];
        $count = 0;
        $paginator = $s3->getPaginator('ListObjectsV2', ['Bucket' => $bucket]);

        foreach ($paginator as $page) {
            if (!empty($page['Contents'])) {
                foreach ($page['Contents'] as $obj) {
                    $key = $obj['Key'];

                    $locationDetails = $this->pathParser->getPathDetails($key);
                    
                    if (!empty($locationDetails)) {
                        extract($locationDetails);
                        $filesBySite[$blogId][$year][$month][$bucket . "/" . $key] = [
                            'size' => $obj['Size']
                        ];
                    }

                    $count++;
                    if ($count % 1000 === 0) {
                        $this->cli::log("[S3 Local Index] Indexed {$count} objects...");
                    }
                }
            }
        }

        $skipped = 0;
        foreach ($filesBySite as $blogId => $years) {
            foreach ($years as $year => $months) {
                foreach ($months as $month => $keys) {
                    $file = "{$cacheDir}/s3-index-{$blogId}-{$year}-{$month}.json";
                    try {
                        $result = $this->fileSystem->mutateIndexFile(
                            $file,
                            static function (array $current) use ($file, $keys, $scanStartedAt): array|false {
                                // Uploads/deletes made while S3 was being scanned must not
                                // be overwritten by a snapshot taken before those changes.
                                clearstatcache(true, $file);
                                if (is_file($file) && filemtime($file) >= $scanStartedAt) {
                                    return false;
                                }
                                return $keys;
                            }
                        );
                    } catch (\Throwable $e) {
                        $this->cli::error("[S3 Local Index] Failed to write {$file}: {$e->getMessage()}");
                        return;
                    }

                    if ($result === false) {
                        $skipped++;
                        $this->cli::warning("Skipped {$file}: changed during S3 scan; next rebuild will refresh it.");
                        continue;
                    }
                    $this->cli::log("Written index for blog {$blogId} {$year}-{$month}. [File: {$file}] [Items: " . count($keys) . "]");
                }
            }
        }

        // A worker could have repopulated the cache from an old index while
        // the scan was running. Invalidate it after publishing the new files.
        $this->cache->clear();
        $this->cli::success("[S3 Local Index] Index created. Total objects: {$count}. Skipped changed months: {$skipped}.");
    }
}
