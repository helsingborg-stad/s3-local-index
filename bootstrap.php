<?php

// Bootstrap file for PHPUnit tests

// Register the autoloader if it exists
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Define constants that might be needed in tests
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Mock WordPress globals and functions if needed
if (!class_exists('WP_CLI')) {
    class WP_CLI {
        public static function add_command($name, $command) {}
        public static function log($message) {}
        public static function success($message) {}
        public static function warning($message) {}
    }
}

// Simple class loader for our tests when autoloader is not available
spl_autoload_register(function ($class) {
    $file = __DIR__ . '/source/php/' . str_replace(['\\', '_'], ['/', '/'], $class) . '.php';
    $file = str_replace('/S3LocalIndex/', '/', $file);
    $file = str_replace('/S3/Local/Index/', '/', $file);
    if (file_exists($file)) {
        require_once $file;
    }
});

// Mock S3 Uploads Plugin class if needed
if (!class_exists('S3_Uploads\Plugin')) {
    eval('
    namespace S3_Uploads {
        class Plugin {
            public static function get_instance() {
                return new static();
            }
            
            public function s3() {
                return new class {
                    public function getPaginator($operation, $args) {
                        return new \ArrayIterator([]);
                    }
                };
            }
            
            public function get_s3_bucket() {
                return "test-bucket";
            }
        }
    }
    ');
}

// Mock WpService classes - first create the base class
if (!class_exists('WpService\WpService')) {
    eval('
    namespace WpService {
        class WpService {
            public function addAction($hook, $callback, $priority = 10, $args = 1) {
                return true;
            }
            
            public function addFilter($hook, $callback, $priority = 10, $args = 1) {
                return true;
            }
            
            public function applyFilters($hook, $value, ...$args) {
                return $value;
            }
            
            public function wpCacheGet($key, $group = "") {
                return false;
            }
            
            public function wpCacheSet($key, $data, $group = "", $expire = 0) {
                return true;
            }
            
            public function wpCacheDelete($key, $group = "") {
                return true;
            }
            
            public function wpCacheFlush() {
                return true;
            }
            
            public function wpCacheFlushGroup($group) {
                return true;
            }
        }
    }
    ');
}

if (!class_exists('WpService\Implementations\FakeWpService')) {
    eval('
    namespace WpService\Implementations {
        class FakeWpService extends \WpService\WpService {
            private $methods = [];
            
            public function __construct(array $methods = []) {
                $this->methods = $methods;
            }
            
            public function addAction($hook, $callback, $priority = 10, $args = 1) {
                return $this->methods["addAction"] ?? true;
            }
            
            public function addFilter($hook, $callback, $priority = 10, $args = 1) {
                return $this->methods["addFilter"] ?? true;
            }
            
            public function applyFilters($hook, $value, ...$args) {
                if (isset($this->methods["applyFilters"]) && is_callable($this->methods["applyFilters"])) {
                    return $this->methods["applyFilters"]($hook, $value, ...$args);
                }
                return $value;
            }
            
            public function wpCacheGet($key, $group = "") {
                return $this->methods["wpCacheGet"] ?? false;
            }
            
            public function wpCacheSet($key, $data, $group = "", $expire = 0) {
                return $this->methods["wpCacheSet"] ?? true;
            }
            
            public function wpCacheDelete($key, $group = "") {
                return $this->methods["wpCacheDelete"] ?? true;
            }
            
            public function wpCacheFlush() {
                return $this->methods["wpCacheFlush"] ?? true;
            }
            
            public function wpCacheFlushGroup($group) {
                return $this->methods["wpCacheFlushGroup"] ?? true;
            }
        }
    }
    ');
}