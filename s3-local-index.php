<?php
/**
 * Plugin Name: S3 Local Index
 * Description: Provides local indexing of S3 files and a CLI command.
 * Version: 0.1.6
 */

use WP_CLI;
use S3_Local_Index\CLI\Command;
use S3_Local_Index\Stream\Wrapper;
use S3_Local_Index\Stream\Reader;
use S3_Local_Index\Cache\CacheFactory;

require_once __DIR__ . '/vendor/autoload.php';

add_action('cli_init', function () {
    WP_CLI::add_command('s3-index', Command::class);
});

add_action('plugins_loaded', function () {
    Reader::setCache(CacheFactory::createDefault());
    Wrapper::init();
}, 20);