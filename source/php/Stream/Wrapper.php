<?php

namespace S3_Local_Index\Stream;

use S3_Uploads\Stream_Wrapper as OriginalStreamWrapper;
use S3_Local_Index\Stream\ReaderInterface;
use S3_Local_Index\Logger\LoggerInterface;

class Wrapper implements WrapperInterface
{
    private static ReaderInterface $reader;
    private static LoggerInterface $logger;

    private const PROTOCOL = 's3';

    public $context;
    private $delegate;
    private static bool $registered = false;

    public function __construct()
    {
        $this->delegate = new OriginalStreamWrapper();
    }

    /**
     * Set dependencies statically.
     *
     * @param ReaderInterface    $reader    Stream reader for file operations
     * @param LoggerInterface    $logger    Logger for debug messages
     */
    public static function setDependencies(ReaderInterface $reader, LoggerInterface $logger): void
    {
        self::$reader = $reader;
        self::$logger = $logger;
    }

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        $protocol = self::PROTOCOL;
        if (in_array($protocol, stream_get_wrappers(), true)) {
            stream_wrapper_unregister($protocol);
        }
        self::$registered = stream_wrapper_register($protocol, static::class, STREAM_IS_URL);
    }

    /**
     * @inheritDoc
     */
    public function url_stat($uri, $flags) : array|false
    {
        $matchingIndexFound = self::$reader->url_stat($uri, $flags);
        if ($matchingIndexFound !== false) {
            return $matchingIndexFound;
        }
        $this->delegate->context = $this->context;
        return $this->delegate->url_stat($uri, $flags);
    }

    /**
     * Magic method to delegate calls to the original stream wrapper.
     *
     * @param string $name Method name
     * @param array  $args Method arguments
     * @return mixed Result of the delegated method call
     * @throws \BadMethodCallException If the method does not exist on the delegate
     */
    public function __call($name, $args)
    {
        if (method_exists($this->delegate, $name)) {
            $this->delegate->context = $this->context;
            return $this->delegate->$name(...$args);
        }

        throw new \BadMethodCallException("Method $name not found on delegate");
    }
}