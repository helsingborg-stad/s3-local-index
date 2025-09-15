<?php

namespace S3_Local_Index\Config;

/**
 * Configuration interface for the S3 Local Index plugin
 */
interface ConfigInterface
{
    /**
     * Check if a feature is enabled via WordPress filters
     *
     * @param string $feature Feature name (optional, defaults to 'enabled')
     * @return bool True if the feature is enabled
     */
    public function isEnabled(string $feature = 'enabled'): bool;

    /**
     * Create a filter key name for WordPress filters
     *
     * @param string $filter Filter name
     * @return string The complete filter key
     */
    public function createFilterKey(string $filter = ""): string;

    /**
     * Get the filter prefix used for this plugin
     *
     * @return string Filter prefix
     */
    public function getFilterPrefix(): string;
}