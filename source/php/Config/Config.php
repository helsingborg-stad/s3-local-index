<?php

namespace S3_Local_Index\Config;

use S3_Local_Index\Config\ConfigInterface;
use WpService\Contracts\ApplyFilters;

/**
 * Configuration provider for S3 Local Index plugin.
 * 
 * This class implements the configuration interface and provides default
 * values while allowing customization through WordPress filters. It determines
 * whether the plugin should be active and sets integration priorities.
 */
class Config implements ConfigInterface
{
    /**
     * Constructor.
     *
     * @param ApplyFilters $wpService    A wp service instance for filter operations.
     * @param string       $filterPrefix The prefix for config filters.
     */
    public function __construct(
        private ApplyFilters $wpService,
        private string $filterPrefix = 'S3_Local_Index/Config',
    ) {
    }

    /**
     * Check if the S3 Local Index plugin is enabled.
     * 
     * The plugin is enabled by default if the S3_Uploads plugin class exists,
     * but this can be overridden via the 'S3_Local_Index/Config/IsEnabled' filter.
     * 
     * @return bool True if the plugin should be active, false otherwise
     */
    public function isEnabled(): bool
    {
        $isEnabled = class_exists('S3_Uploads\Plugin');
        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            $isEnabled
        );
    }

    /**
     * Get the priority for CLI command registration.
     * 
     * Returns the priority level used when registering CLI commands with WordPress.
     * Can be customized via the 'S3_Local_Index/Config/GetCliPriority' filter.
     * 
     * @return int The priority level for WordPress CLI hooks (default: 10)
     */
    public function getCliPriority(): int
    {
        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            10
        );
    }

    /**
     * Get the priority for plugin initialization.
     * 
     * Returns the priority level used when initializing the plugin functionality.
     * Can be customized via the 'S3_Local_Index/Config/GetPluginPriority' filter.
     * 
     * @return int The priority level for WordPress plugin initialization hooks (default: 20)
     */
    public function getPluginPriority(): int
    {
        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            20
        );
    }

    /**
     * Get the cache directory path.
     * 
     * Returns a unique cache directory for this site to prevent collisions
     * when multiple sites run on the same server. Uses ABSPATH root to
     * generate a UUID for uniqueness.
     * Can be customized via the 'S3_Local_Index/Config/GetCacheDirectory' filter.
     * 
     * @return string The directory path for cache storage
     */
    public function getCacheDirectory(): string
    {
        if(defined('ABSPATH')) {
            $siteUuid           = substr(md5(constant('ABSPATH')), 0, 8); // Use first 8 characters of MD5 hash
            $defaultCacheDir    = sys_get_temp_dir() . "/s3-index-{$siteUuid}";
        } else {
            // Fallback if ABSPATH is not defined
            $defaultCacheDir    = sys_get_temp_dir() . "/s3-index";
        }
        
        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            $defaultCacheDir
        );
    }

    /**
     * Create a filter key with the configured prefix.
     *
     * @param  string $filter The filter name to append to the prefix
     * @return string The complete filter key
     */
    public function createFilterKey(string $filter = ""): string
    {
        return $this->filterPrefix . "/" . ucfirst($filter);
    }
}