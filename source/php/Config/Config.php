<?php

namespace S3_Local_Index\Config;

use S3_Local_Index\Config\ConfigInterface;
use WpService\Contracts\ApplyFilters;

/**
 * Configuration provider for S3 Local Index plugin.
 * 
 * Supplies all configuration values with defaults,
 * while allowing WordPress filters to override them.
 */
class Config implements ConfigInterface
{
    public function __construct(
        private ApplyFilters $wpService,
        private string $filterPrefix = 'S3_Local_Index/Config',
    ) {
    }

    /** 
     * Check if the S3 Local Index plugin is enabled.
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
     * Check if debug logging is enabled.
     */
    public function isDebugEnabled(): bool
    {
        $isEnabled = defined('WP_DEBUG') && WP_DEBUG;
        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            $isEnabled
        );
    }

    /** 
     * Whether caching is enabled for stream wrapper calls.
     * 
     * Default: true if plugin enabled.
     */
    public function isCacheEnabled(): bool
    {
        $default = $this->isEnabled();
        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            $default
        );
    }

    /**
     * Get cache lifetime (in seconds).
     * 
     * Default: 3600*24 seconds (1 day).
     */
    public function getCacheTtl(): int
    {
        $default = 3600 * 24;
        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            $default
        );
    }

    /**
     * Get cache directory path.
     * 
     * Returns a unique directory based on ABSPATH hash.
     */
    public function getCacheDirectory(): string
    {
        if (defined('ABSPATH')) {
            $siteUuid = substr(md5(constant('ABSPATH')), 0, 8);
            $defaultCacheDir = sys_get_temp_dir() . "/s3-index-{$siteUuid}";
        } else {
            $defaultCacheDir = sys_get_temp_dir() . "/s3-index";
        }

        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            $defaultCacheDir
        );
    }

    /**
     * Get priority for CLI registration.
     */
    public function getCliPriority(): int
    {
        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            10
        );
    }

    /**
     * Get priority for plugin initialization.
     */
    public function getPluginPriority(): int
    {
        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            20
        );
    }

    /**
     * Build a full filter key from prefix + function name.
     */
    public function createFilterKey(string $filter = ''): string
    {
        return $this->filterPrefix . '/' . ucfirst($filter);
    }
}