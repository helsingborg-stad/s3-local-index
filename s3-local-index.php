<?php

namespace S3_Local_Index;

use S3_Uploads\Plugin;
use WP_CLI;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 's3-index', CLI_Command::class );
}

/**
 * CLI command to create S3 index files.
 */
class CLI_Command {

    /**
     * Creates the S3 index and stores it in the system temp directory.
     *
     * ## EXAMPLES
     *
     *     wp s3-index create
     *
     * @when after_wp_load
     */
    public function create( $args = [], $assoc_args = [] ) {
        if ( ! class_exists( 'S3_Uploads\\Plugin' ) ) {
            WP_CLI::error( 'S3_Uploads plugin not loaded yet.' );
            return;
        }

        $s3 = Plugin::get_instance()->s3();
        $bucket = Plugin::get_instance()->get_s3_bucket();

        WP_CLI::log("[S3 Local Index] Creating index for bucket: {$bucket}");

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
                    // parse blog/year/month
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

        // Write each blog/year/month file separately
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
    }
}

/**
 * Stream wrapper that reads from S3 index files instead of directly listing S3.
 */
class Stream_Wrapper {

    /** @var bool Wrapper registered */
    private static bool $registered = false;

    /** @var array<string,bool> Loaded index for current path */
    private static array $index = [];

    private $position = 0;
    private $key = '';
    public $context;

    /** @var array Current dir keys for dir_ functions */
    private $dir_keys = [];
    private $dir_position = 0;

    /**
     * Registers the stream wrapper if S3 plugin is available.
     */
    public static function init(): void {
        if (!class_exists('S3_Uploads\Plugin')) {
            error_log('[S3 Local Index] S3_Uploads plugin not found, wrapper not registered.');
            return;
        }

        if (!self::$registered) {
            if (in_array('s3', stream_get_wrappers(), true)) {
                @stream_wrapper_unregister('s3');
                error_log('[S3 Local Index] Existing s3 wrapper unregistered.');
            }

            if (!stream_wrapper_register('s3', __CLASS__)) {
                error_log('[S3 Local Index] Failed to register stream wrapper.');
                return;
            }

            self::$registered = true;
            error_log('[S3 Local Index] Stream wrapper registered.');
        }
    }

    /**
     * Load the index for a given S3 path on demand.
     */
    private static function load_index_for_path(string $path): array {
        // Normalize path
        $path = ltrim($path, '/');

        // Regex handles both multisite and single-site uploads
        if (!preg_match('#uploads(?:/networks/\d+/sites/(\d+))?/(\d{4})/(\d{2})/#', $path, $m)) {
            return [];
        }

        // Blog ID: use matched value if multisite, else default to 1
        $blog_id = $m[1] ?? '1';
        $year    = $m[2];
        $month   = $m[3];

        // Construct per-site, per-month index file path
        $file = sys_get_temp_dir() . "/s3-index-temp/s3-index-{$blog_id}-{$year}-{$month}.json";

        if (!file_exists($file)) {
            return [];
        }

        $data = file_get_contents($file);
        return json_decode($data, true) ?: [];
    }

    /* ---------------- Stream wrapper methods ---------------- */

    public function stream_open($path, $mode, $options, &$opened_path) {
        self::$index = self::load_index_for_path($path);
        $normalized = $this->normalize_path($path);
        if (!isset(self::$index[$normalized])) {
            return false;
        }
        $this->key = $normalized;
        $this->position = 0;
        return true;
    }

    public function stream_read($count) {
        $data = file_get_contents('s3://' . $this->key);
        $chunk = substr($data, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_eof() {
        $data = file_get_contents('s3://' . $this->key);
        return $this->position >= strlen($data);
    }

    public function stream_stat() {
        return [];
    }

    public function url_stat($path, $flags) {
        self::$index = self::load_index_for_path($path);
        $normalized = $this->normalize_path($path);
        if (isset(self::$index[$normalized])) {
            return ['size' => 1, 'mtime' => time()];
        }
        return false;
    }

    public function dir_opendir($path, $options) {
        self::$index = self::load_index_for_path($path);
        $this->dir_keys = [];
        $prefix = rtrim(str_replace('s3://', '', $path), '/') . '/';
        foreach (self::$index as $key => $_) {
            if (str_starts_with($key, $prefix)) {
                $this->dir_keys[] = substr($key, strlen($prefix));
            }
        }
        $this->dir_position = 0;
        return true;
    }

    public function dir_readdir() {
        if ($this->dir_position < count($this->dir_keys)) {
            return $this->dir_keys[$this->dir_position++];
        }
        return false;
    }

    public function dir_closedir() {
        $this->dir_keys = [];
    }

    private function normalize_path($path): string {
        return ltrim(str_replace('s3://', '', $path), '/');
    }
}

/* ---------------- Usage ---------------- */

// Hook into plugins_loaded to ensure S3 is available
add_action('plugins_loaded', function() {
    Stream_Wrapper::init();
}, 20);