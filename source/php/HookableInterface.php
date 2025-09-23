<?php

namespace S3_Local_Index;

/**
 * Interface for classes that can register WordPress hooks.
 * 
 * This interface ensures that classes implementing it provide a standardized way
 * to register their WordPress action and filter hooks.
 */
interface HookableInterface
{
    /**
     * Add WordPress hooks (actions and filters) for this class.
     * 
     * This method should contain all the WordPress hook registrations needed
     * for the implementing class to function properly.
     * 
     * @return void
     */
    public function addHooks(): void;
}