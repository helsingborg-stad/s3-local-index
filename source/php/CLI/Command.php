<?php

namespace S3_Local_Index\CLI;

use S3_Uploads\Plugin;
use S3_Local_Index\Stream\Reader;
use WP_CLI;

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

        $temp_dir = sys_get_temp_dir() . '/s3-index-temp';
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0777, true);
        }

        $files_by_site = [];
        $count = 0;
        $paginator = $s3->getPaginator('ListObjectsV2', ['Bucket' => $bucket]);

        foreach ($paginator as $page) {
            if (!empty($page['Contents'])) {
                foreach ($page['Contents'] as $obj) {
                    $key = $obj['Key'];
                    if (preg_match('#uploads/networks/\d+/sites/(\d+)/(\d{4})/(\d{2})/#', $key, $m)) {
                        $blog_id = $m[1];
                        $year = $m[2];
                        $month = $m[3];
                        $files_by_site[$blog_id][$year][$month][] = $key;
                    }
                    $count++;
                    if ($count % 1000 === 0) {
                        WP_CLI::log("[S3 Local Index] Indexed {$count} objects...");
                    }
                }
            }
        }

        foreach ($files_by_site as $blog_id => $years) {
            foreach ($years as $year => $months) {
                foreach ($months as $month => $keys) {
                    $file = "{$temp_dir}/s3-index-{$blog_id}-{$year}-{$month}.json";
                    file_put_contents($file, json_encode($keys, JSON_PRETTY_PRINT));
                    WP_CLI::log("Written index for blog {$blog_id} {$year}-{$month}, count: " . count($keys));
                }
            }
        }

        WP_CLI::success("Index created successfully. Total objects: {$count}");
        WP_CLI::log("[S3 Local Index] Cache will be populated on next access.");
    }
}