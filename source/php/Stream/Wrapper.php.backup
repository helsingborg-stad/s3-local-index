<?php
declare(strict_types=1);

namespace S3_Local_Index\Stream;

use S3_Local_Index\Stream\ReaderInterface;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Stream\WrapperInterface;
use S3_Local_Index\Parser\PathParserInterface;

class Wrapper implements WrapperInterface
{
    private static WrapperInterface    $reader;
    private static PathParserInterface $pathParser;
    private static LoggerInterface     $logger;
    private static WrapperInterface    $delegate;

    private const PROTOCOL = 's3';
    public $context;

    public function __construct() {}

    /**
     * Set dependencies statically.
     */
    public static function setDependencies(
        WrapperInterface $reader,
        PathParserInterface $pathParser,
        LoggerInterface $logger,
        WrapperInterface $delegate
    ): void {
        self::$reader     = $reader;
        self::$pathParser = $pathParser;
        self::$logger     = $logger;
        self::$delegate   = $delegate;
    }

    public static function register(): void
    {
        static $registered = false;
        if ($registered === true) {
            return;
        }

        $protocol = self::PROTOCOL;
        if (in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
            stream_wrapper_unregister($protocol);
        }
        $registered = stream_wrapper_register(self::PROTOCOL, static::class, STREAM_IS_URL);

        if ($registered) {
            self::$logger->log("Stream wrapper for {".self::PROTOCOL."} successfully registered.");
        } else {
            self::$logger->log("Failed to register stream wrapper for {".self::PROTOCOL."}.");
        }
    }

    /**
     * File exists check handler.
     * 
     * @inheritDoc
     */
    public function url_stat(string $uri, int $flags): array|false
    {
        $response       = null;
        $uri            = self::$pathParser->normalizePath($uri);
        $isFileExists   = fn($uri, int $flags) =>
            pathinfo($uri, PATHINFO_EXTENSION) !== '' &&
            ($flags & STREAM_URL_STAT_QUIET) !== 0;

        //Should not be handled by us, delegate
        if (!$isFileExists($uri, $flags)) {
            self::$logger->log("Delegating url_stat for non-file_exists query: $uri");
            return $this->__call(
                'url_stat',
                [self::$pathParser->normalizePathWithProtocol($uri), $flags]
            );
        }

        //Handle file_exists check
        try {
            $response = self::$reader->url_stat($uri, $flags);
        } catch (\Throwable $e) {
            self::$logger->log("url_stat failed: " . $e->getMessage());
        } finally {
            return match (true) {
                is_array($response)             => $response,
                $response === 'entry_not_found' => false,
                default                         => $this->__call(
                    'url_stat',
                    [self::$pathParser->normalizePathWithProtocol($uri), $flags]
                ),
            };
        }
    }

    /**
     * Magic method to delegate calls to the original stream wrapper.
     *
     * @param string $name Method name
     * @param array<int,mixed> $args Arguments
     * @return mixed
     */
    public function __call(string $name, array $args): mixed
    {
        if (method_exists(self::$delegate, $name)) {
            if (is_resource($this->context)) {
                self::$delegate->context = $this->context;
            }
            return self::$delegate->$name(...$args);
        }
        throw new \BadMethodCallException("Method $name not found on delegate");
    }
}