<?php

namespace S3_Local_Index\CLI;

use S3_Uploads\Plugin;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\Cache\CacheFactory;
use WP_CLI;
use Exception;
use S3_Local_Index\Rebuild\RebuildTrackerInterface;
use WpService\WpService;
use S3LocalIndex\Parser\ParserInterface;
use S3_Local_Index\Logger\LoggerInterface;

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
     * @param ParserInterface|null                        $parser         Parser for path operations (optional)
     * @param LoggerInterface|null                        $logger         Logger for debug messages (optional)
     */
    public function __construct(
        private WpService $wpService, 
        private Plugin $s3, 
        private $cli,
        private FileSystemInterface $fileSystem,
        private RebuildTrackerInterface $rebuildTracker,
        private CacheFactory $cacheFactory,
        private ParserInterface $parser,
        private LoggerInterface $logger
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

                    $locationDetails = $this->parser->getPathDetails($key);
                    
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
                    $formattedMonth = sprintf('%02d', $month); // Ensure leading zero format
                    $file = "{$cacheDir}/s3-index-{$blogId}-{$year}-{$formattedMonth}.json";
                    $this->fileSystem->filePutContents($file, json_encode($keys, JSON_PRETTY_PRINT));
                    $this->cli::log("Written index for blog {$blogId} {$year}-{$formattedMonth}. [File: {$file}] [Items: " . count($keys) . "]");
                }
            }
        }

        $this->cli::success("[S3 Local Index] Index created successfully. Total objects: {$count}");
        $this->cli::log("[S3 Local Index] Cache will be populated on next access.");
    }
}