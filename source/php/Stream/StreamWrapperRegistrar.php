<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Logger\LoggerInterface;

/**
 * Service for registering and managing stream wrappers.
 * 
 * This class handles the registration of custom stream wrappers with PHP's
 * stream system, providing proper logging and error handling.
 */
class StreamWrapperRegistrar implements StreamWrapperRegistrarInterface
{
    private static array $registeredProtocols = [];

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Register a stream wrapper for the given protocol.
     *
     * @param  string $protocol  The protocol to register (e.g., 's3')
     * @param  string $className The fully qualified class name of the wrapper
     * @return bool True if registration was successful, false otherwise
     */
    public function register(string $protocol, string $className): bool
    {
        // Prevent duplicate registrations
        if (isset(self::$registeredProtocols[$protocol])) {
            $this->logger->log("Stream wrapper for {$protocol} already registered.");
            return true;
        }

        // Unregister existing wrapper if present
        if ($this->isRegistered($protocol)) {
            $this->unregister($protocol);
        }

        $success = stream_wrapper_register($protocol, $className, STREAM_IS_URL);

        if ($success) {
            self::$registeredProtocols[$protocol] = $className;
            $this->logger->log("Stream wrapper for {$protocol} successfully registered.");
        } else {
            $this->logger->log("Failed to register stream wrapper for {$protocol}.");
        }

        return $success;
    }

    /**
     * Unregister a stream wrapper for the given protocol.
     *
     * @param  string $protocol The protocol to unregister
     * @return bool True if unregistration was successful, false otherwise
     */
    public function unregister(string $protocol): bool
    {
        if (!$this->isRegistered($protocol)) {
            return true;
        }

        $success = stream_wrapper_unregister($protocol);
        
        if ($success) {
            unset(self::$registeredProtocols[$protocol]);
            $this->logger->log("Stream wrapper for {$protocol} successfully unregistered.");
        } else {
            $this->logger->log("Failed to unregister stream wrapper for {$protocol}.");
        }

        return $success;
    }

    /**
     * Check if a protocol is already registered.
     *
     * @param  string $protocol The protocol to check
     * @return bool True if the protocol is registered, false otherwise
     */
    public function isRegistered(string $protocol): bool
    {
        return in_array($protocol, stream_get_wrappers(), true);
    }
}