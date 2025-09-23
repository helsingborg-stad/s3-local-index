<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Stream\ReaderInterface;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Stream\WrapperInterface;
use S3_Local_Index\Parser\PathParserInterface;

class Wrapper implements WrapperInterface
{
    private static ReaderInterface      $reader;
    private static PathParserInterface  $pathParser;
    private static LoggerInterface      $logger;

    private const PROTOCOL = 's3';

    public $context;
    private static $delegate;
    private static bool $registered = false;

    public function __construct(){}

    /**
     * Set dependencies statically.
     *
     * @param ReaderInterface    $reader    Stream reader for file operations
     * @param LoggerInterface    $logger    Logger for debug messages
     */
    public static function setDependencies(ReaderInterface $reader, PathParserInterface $pathParser, LoggerInterface $logger, WrapperInterface $delegate): void
    {
        self::$reader       = $reader;
        self::$pathParser   = $pathParser;
        self::$logger       = $logger;
        self::$delegate     = $delegate;
    }

    public static function register(): void
    {
        if (self::$registered === true) {
            return;
        }

        $protocol = self::PROTOCOL;
        if (in_array($protocol, stream_get_wrappers(), true)) {
            stream_wrapper_unregister($protocol);
        }
        self::$registered = stream_wrapper_register($protocol, static::class, STREAM_IS_URL);

        if(self::$registered) {
            self::$logger->log("Stream wrapper for {$protocol} successfully registered.");
        } else {
            self::$logger->log("Failed to register stream wrapper for {$protocol}.");
        }
    }

    /**
     * @inheritDoc
     */
    public function url_stat($uri, $flags): array|false
    {
        $uri      = self::$pathParser->normalizePath($uri); 
        $isFile   = pathinfo($uri, PATHINFO_EXTENSION) !== '';
        $isExists = ($flags & STREAM_URL_STAT_QUIET) !== 0; // true if it's a file_exists/is_file/is_dir check

        if (!$isFile || !$isExists) {
            self::$delegate->context = $this->context;
            return self::$delegate->url_stat($uri, $flags);
        }

        $response = self::$reader->url_stat($uri, $flags);

        return match (true) {
            //Check                         //Return
            is_array($response)             => $response,
            $response === 'entry_not_found' => false,
            default => (function () use ($uri, $flags) {
                self::$delegate->context = $this->context;
                return self::$delegate->url_stat($uri, $flags);
            })()
        };
    }

    /**
     * @inheritDoc
     */
    public function stream_flush(): bool
    {
        self::$delegate->context = $this->context;
        $result = self::$delegate->stream_flush();

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

        self::$logger->log("stream_flush called, result: " . ($result ? 'true' : 'false'));

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function unlink(string $path): bool
    {
        self::$delegate->context = $this->context;
        $result = self::$delegate->unlink($path);

        if ($result) {
            try {
                self::$reader->removeFromIndex($path);
                self::$logger->log("Index updated (removed) for {$path}");
            } catch (\Throwable $e) {
                self::$logger->log("Failed removing from index: " . $e->getMessage());
            }
        }

        self::$logger->log("unlink called for {$path}, result: " . ($result ? 'true' : 'false'));

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
        if (method_exists(self::$delegate, $name)) {

            self::$logger->log("Delegating call to {$name} with args: " . json_encode($args));

            self::$delegate->context = $this->context;
            return self::$delegate->$name(...$args);
        }

        throw new \BadMethodCallException("Method $name not found on delegate");
    }
}