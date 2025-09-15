<?php
/**
 * Plugin Name: S3 Local Index
 * Description: Provides local indexing of S3 files and a CLI command with cache flushing capabilities.
 * Version: 0.1.6
 * 
 * CLI Commands:
 * - wp s3-index create                     : Create full S3 index
 * - wp s3-index flush [path] [--add]       : Flush cache for specific path, optionally add to rebuild list
 * - wp s3-index rebuild [--clear] [--all]  : Rebuild specific indexes from rebuild list
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