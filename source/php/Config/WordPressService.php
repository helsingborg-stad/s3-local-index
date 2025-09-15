<?php

namespace S3_Local_Index\Config;

/**
 * WordPress service implementation
 */
class WordPressService implements WordPressServiceInterface
{
    /**
     * Apply WordPress filters
     *
     * @param string $hook_name The name of the filter hook
     * @param mixed $value The value to filter
     * @param mixed ...$args Additional arguments
     * @return mixed The filtered value
     */
    public function applyFilters(string $hook_name, $value, ...$args)
    {
        // Use WordPress apply_filters function if available
        if (function_exists('apply_filters')) {
            return apply_filters($hook_name, $value, ...$args);
        }
        
        // Fallback if WordPress functions are not available
        return $value;
    }
}