<?php

namespace S3LocalIndex\Config;

use S3LocalIndex\Config\ConfigInterface;
use WpService\Contracts\ApplyFilters;

class Config implements ConfigInterface
{
    /**
     * Constructor.
     *
     * @param ApplyFilters $wpService               A wp service instance.
     * @param string $filterPrefix                  The prefix for config filters.
     */
    public function __construct(
        private ApplyFilters $wpService,
        private string $filterPrefix = 'S3LocalIndex/Config',
    ) {
    }

    /**
     * If the image conversion is enabled.
     */
    public function isEnabled(): bool
    {
        $isEnabled = class_exists('S3_Uploads\Plugin');
        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            $isEnabled
        );
    }

    public function getCliPriority(): int
    {
        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            10
        );
    }

    public function getPluginPriority(): int
    {
        return $this->wpService->applyFilters(
            $this->createFilterKey(__FUNCTION__),
            20
        );
    }

    /**
     * Create a prefix for image conversion filter.
     *
     * @return string
     */
    public function createFilterKey(string $filter = ""): string
    {
        return $this->filterPrefix . "/" . ucfirst($filter);
    }
}