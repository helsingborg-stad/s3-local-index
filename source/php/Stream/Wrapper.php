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
     * @inheritDoc
     */
    public function stream_flush(): bool
    {
        $this->delegate->context = $this->context;
        $result = $this->delegate->stream_flush();

        if ($result) {
            try {
                $options = stream_context_get_options($this->context);
                $bucket  = $options['s3']['Bucket'] ?? null;
                $key     = $options['s3']['Key'] ?? null;

                if ($bucket && $key) {
                    $path = "s3://{$bucket}/{$key}";
                    self::$reader->updateIndex($path);
                    self::$logger->log("Index updated for {$path}");
                }
            } catch (\Throwable $e) {
                self::$logger->log("Failed updating index: " . $e->getMessage());
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function unlink(string $path): bool
    {
        $this->delegate->context = $this->context;
        $result = $this->delegate->unlink($path);

        if ($result) {
            try {
                self::$reader->removeFromIndex($path);
                self::$logger->log("Index updated (removed) for {$path}");
            } catch (\Throwable $e) {
                self::$logger->log("Failed removing from index: " . $e->getMessage());
            }
        }

        return $result;
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