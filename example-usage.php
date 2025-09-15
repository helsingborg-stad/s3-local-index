<?php
/**
 * Example usage of the S3 Local Index configuration system
 * 
 * This file demonstrates how to use the new configuration system
 * in your WordPress theme or plugin.
 */

use S3_Local_Index\Config\ConfigFactory;

// Example 1: Basic usage - check if plugin is enabled
add_action('init', function() {
    $config = ConfigFactory::createDefault();
    
    if ($config->isEnabled()) {
        // Plugin is enabled, safe to use S3 Local Index features
        error_log('S3 Local Index is enabled');
    } else {
        // Plugin is disabled via filters
        error_log('S3 Local Index is disabled');
    }
});

// Example 2: Disable the plugin in admin area
add_filter('s3_local_index/Enabled', function($enabled) {
    if (is_admin()) {
        return false; // Disable in admin
    }
    return $enabled;
});

// Example 3: Disable CLI commands on production
add_filter('s3_local_index/CliCommands', function($enabled) {
    if (defined('WP_ENV') && WP_ENV === 'production') {
        return false; // Disable CLI commands in production
    }
    return $enabled;
});

// Example 4: Custom feature flags
add_action('wp_loaded', function() {
    $config = ConfigFactory::createDefault();
    
    // Check for a custom feature
    if ($config->isEnabled('advancedCaching')) {
        // Advanced caching is enabled
        // The filter would be: s3_local_index/AdvancedCaching
    }
    
    // Check for another custom feature  
    if ($config->isEnabled('debugMode')) {
        // Debug mode is enabled
        // The filter would be: s3_local_index/DebugMode
    }
});

// Example 5: Enable a custom feature conditionally
add_filter('s3_local_index/AdvancedCaching', function($enabled) {
    // Enable advanced caching only if we have enough memory
    $memory_limit = ini_get('memory_limit');
    $memory_bytes = wp_convert_hr_to_bytes($memory_limit);
    
    return $memory_bytes >= (512 * 1024 * 1024); // 512MB
});

// Example 6: Debug mode based on WP_DEBUG
add_filter('s3_local_index/DebugMode', function($enabled) {
    return defined('WP_DEBUG') && WP_DEBUG;
});