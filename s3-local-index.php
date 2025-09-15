<?php
/**
 * Plugin Name: S3 Local Index
 * Description: Provides local indexing of S3 files and a CLI command.
 * Version: 1.0.0
 */

use S3_Local_Index\CLI\Command;
use S3_Local_Index\Stream\Wrapper;
use WP_CLI;

require_once __DIR__ . '/vendor/autoload.php';

add_action('cli_init', function () {
    WP_CLI::add_command('s3-index', Command::class);
});

add_action('plugins_loaded', function () {
    Wrapper::init();
}, 20);