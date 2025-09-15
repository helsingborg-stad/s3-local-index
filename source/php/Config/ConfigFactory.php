<?php

namespace S3_Local_Index\Config;

/**
 * Factory for creating configuration instances with dependency injection
 */
class ConfigFactory
{
    private static ?ConfigInterface $instance = null;

    /**
     * Create a default configuration instance
     *
     * @return ConfigInterface
     */
    public static function createDefault(): ConfigInterface
    {
        if (self::$instance === null) {
            $wpService = new WordPressService();
            self::$instance = new Config($wpService);
        }
        
        return self::$instance;
    }

    /**
     * Create a configuration instance with custom WordPress service
     *
     * @param WordPressServiceInterface $wpService Custom WordPress service
     * @param string $filterPrefix Optional filter prefix
     * @return ConfigInterface
     */
    public static function create(WordPressServiceInterface $wpService, string $filterPrefix = 's3_local_index'): ConfigInterface
    {
        return new Config($wpService, $filterPrefix);
    }

    /**
     * Reset the singleton instance (useful for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}