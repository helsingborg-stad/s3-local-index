<?php

namespace S3_Local_Index\Config;

/**
 * WordPress service interface for dependency injection
 */
interface WordPressServiceInterface
{
    /**
     * Apply WordPress filters
     *
     * @param string $hook_name The name of the filter hook
     * @param mixed $value The value to filter
     * @param mixed ...$args Additional arguments
     * @return mixed The filtered value
     */
    public function applyFilters(string $hook_name, $value, ...$args);
}