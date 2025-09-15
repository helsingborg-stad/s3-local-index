<?php

// Bootstrap file for PHPUnit tests

// Register the autoloader
require_once __DIR__ . '/vendor/autoload.php';

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

// Mock S3 Uploads Plugin class if needed
if (!class_exists('S3_Uploads\Plugin')) {
    namespace S3_Uploads {
        class Plugin {
            public static function get_instance() {
                return new static();
            }
            
            public function s3() {
                return new class {
                    public function getPaginator($operation, $args) {
                        return [];
                    }
                };
            }
            
            public function get_s3_bucket() {
                return 'test-bucket';
            }
        }
    }
}