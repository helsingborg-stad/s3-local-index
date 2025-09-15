<?php

namespace S3_Local_Index\Config;

/**
 * Configuration class for the S3 Local Index plugin
 */
class Config implements ConfigInterface
{
    private WordPressServiceInterface $wpService;
    private string $filterPrefix;

    /**
     * Constructor
     *
     * @param WordPressServiceInterface $wpService WordPress service for dependency injection
     * @param string $filterPrefix Filter prefix for this plugin (default: 's3_local_index')
     */
    public function __construct(WordPressServiceInterface $wpService, string $filterPrefix = 's3_local_index')
    {
        $this->wpService = $wpService;
        $this->filterPrefix = $filterPrefix;
    }

    /**
     * Check if a feature is enabled via WordPress filters
     *
     * @param string $feature Feature name (optional, defaults to 'enabled')
     * @return bool True if the feature is enabled
     */
    public function isEnabled(string $feature = 'enabled'): bool
    {
        return $this->wpService->applyFilters(
            $this->createFilterKey($feature),
            true
        );
    }

    /**
     * Create a filter key name for WordPress filters
     *
     * @param string $filter Filter name
     * @return string The complete filter key
     */
    public function createFilterKey(string $filter = ""): string
    {
        if (empty($filter)) {
            return $this->filterPrefix;
        }
        
        return $this->filterPrefix . "/" . ucfirst($filter);
    }

    /**
     * Get the filter prefix used for this plugin
     *
     * @return string Filter prefix
     */
    public function getFilterPrefix(): string
    {
        return $this->filterPrefix;
    }
}