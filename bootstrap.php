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