<?php
/**
 * Plugin Name: S3 Local Index
 * Description: Provides local indexing of S3 files and a CLI command.
 * Version: 0.1.6
 */

use S3LocalIndex\App;

require_once __DIR__ . '/vendor/autoload.php';

$wpService  = new WpService\WpService();
$config     = new S3LocalIndex\Config\Config($wpService);

$app = new App($wpService, $config);
$app->addHooks();