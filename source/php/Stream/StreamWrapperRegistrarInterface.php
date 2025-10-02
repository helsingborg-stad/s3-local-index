<?php

namespace S3_Local_Index\Stream;

/**
 * Interface for stream wrapper registration services.
 * 
 * Handles the registration and unregistration of custom stream wrappers
 * with the PHP stream system.
 */
interface StreamWrapperRegistrarInterface
{
    /**
     * Register a stream wrapper for the given protocol.
     *
     * @param string $protocol The protocol to register (e.g., 's3')
     * @param string $className The fully qualified class name of the wrapper
     * @return bool True if registration was successful, false otherwise
     */
    public function register(string $protocol, string $className): bool;

    /**
     * Unregister a stream wrapper for the given protocol.
     *
     * @param string $protocol The protocol to unregister
     * @return bool True if unregistration was successful, false otherwise
     */
    public function unregister(string $protocol): bool;

    /**
     * Check if a protocol is already registered.
     *
     * @param string $protocol The protocol to check
     * @return bool True if the protocol is registered, false otherwise
     */
    public function isRegistered(string $protocol): bool;
}