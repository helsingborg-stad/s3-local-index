<?php
declare(strict_types=1);

namespace S3_Local_Index\Stream;

use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Stream\StreamWrapperInterface;
use S3_Local_Index\Parser\PathParserInterface;

class StreamWrapperProxy implements StreamWrapperInterface
{
    private static StreamWrapperInterface    $streamWrapperIndexed;
    private static StreamWrapperInterface    $streamWrapperOriginal;

    private static PathParserInterface $pathParser;
    private static LoggerInterface     $logger;

    public $context;

    /**
     * Set dependencies statically.
     */
    public static function setDependencies(
        StreamWrapperInterface $streamWrapperIndexed,
        StreamWrapperInterface $streamWrapperOriginal,

        PathParserInterface $pathParser,
        LoggerInterface $logger,
    ): void {
        self::$streamWrapperIndexed    = $streamWrapperIndexed;
        self::$streamWrapperOriginal   = $streamWrapperOriginal;

        self::$pathParser = $pathParser;
        self::$logger     = $logger;
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
            $response = self::$streamWrapperIndexed->url_stat($uri, $flags);
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
        if (method_exists(self::$streamWrapperOriginal, $name)) {
            if (is_resource($this->context)) {
                self::$streamWrapperOriginal->context = $this->context;
            }
            return self::$streamWrapperOriginal->$name(...$args);
        }
        throw new \BadMethodCallException("Method $name not found on delegate");
    }
}