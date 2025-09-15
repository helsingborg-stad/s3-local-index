<?php

namespace S3LocalIndex\Config;

/**
 * Configuration interface for S3 Local Index plugin.
 * 
 * This interface defines the contract for configuration providers,
 * allowing the plugin to determine its operational parameters and
 * integration priorities within WordPress.
 */
interface ConfigInterface
{
    /**
     * Check if the S3 Local Index plugin is enabled.
     * 
     * @return bool True if the plugin should be active, false otherwise
     */
    public function isEnabled(): bool;

    /**
     * Get the priority for CLI command registration.
     * 
     * @return int The priority level for WordPress CLI hooks
     */
    public function getCliPriority(): int;

    /**
     * Get the priority for plugin initialization.
     * 
     * @return int The priority level for WordPress plugin initialization hooks
     */
    public function getPluginPriority(): int;
}