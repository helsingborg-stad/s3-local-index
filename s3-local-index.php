<?php
/**
 * Plugin Name: S3 Local Index
 * Description: Provides local indexing of S3 files and a CLI command.
 * Version: 0.1.3
 */

use S3_Local_Index\CLI\Command;
use S3_Local_Index\Stream\Wrapper;

require_once __DIR__ . '/vendor/autoload.php';

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('s3-index', Command::class);
}

// Hook into plugins_loaded to ensure S3 is available
add_action('plugins_loaded', function () {
    Wrapper::init();
}, 20);