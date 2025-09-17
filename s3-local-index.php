<?php
/**
 * Plugin Name: S3 Local Index
 * Description: Provides local indexing of S3 files and a CLI command with cache flushing capabilities.
 * Version: 0.2.23
 * 
 * CLI Commands:
 * - wp s3-index create                     : Create full S3 index
 * - wp s3-index flush [path] [--add]       : Flush cache for specific path, optionally add to rebuild list
 * - wp s3-index rebuild [--clear] [--all]  : Rebuild specific indexes from rebuild list
 */

use S3LocalIndex\App;
use WpService\Implementations\NativeWpService;

require_once __DIR__ . '/vendor/autoload.php';

$wpService  = new NativeWpService();
$config     = new S3LocalIndex\Config\Config($wpService);

$app = new App($wpService, $config);
$app->addHooks();