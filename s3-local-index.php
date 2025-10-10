<?php
/**
 * Plugin Name: S3 Local Index
 * Description: Provides local indexing of S3 files and a CLI command with cache flushing capabilities.
 * Version: 1.1.11
 */

use S3_Local_Index\App;
use WpService\Implementations\NativeWpService;

if(file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$wpService  = new NativeWpService();
$config     = new S3_Local_Index\Config\Config($wpService);

$app = new App($wpService, $config);
$app->addHooks();