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
        $response = null;
        $uri = self::$pathParser->normalizePath($uri);
        $isFileExists = fn($uri, int $flags) =>
            pathinfo($uri, PATHINFO_EXTENSION) !== '' &&
            ($flags & STREAM_URL_STAT_QUIET) !== 0;
        $isDirExists = fn($uri, int $flags) =>
            pathinfo($uri, PATHINFO_EXTENSION) === '' &&
            ($flags & STREAM_URL_STAT_QUIET) !== 0;

        // Should not be handled by us, delegate
        if (!$isFileExists($uri, $flags) && !$isDirExists($uri, $flags)) {
            self::$logger->log("Delegating url_stat for non-file/dir query: $uri");
            return $this->__call(
                'url_stat',
                [self::$pathParser->normalizePathWithProtocol($uri), $flags]
            );
        }

        // Directory check: prefer the local index. If the index is missing we delegate
        // to the original stream wrapper for a more thorough check.
        if ($isDirExists($uri, $flags)) {
            try {
                $response = self::$streamWrapperIndexed->url_stat($uri, $flags);
            } catch (\Throwable $e) {
                self::$logger->log("url_stat (dir) failed: " . $e->getMessage());
            }

            if (is_array($response)) {
                return $response;
            }

            if ($response === 'index_not_found') {
                // Index not present — fall back to original resolver
                return $this->__call(
                    'url_stat',
                    [self::$pathParser->normalizePathWithProtocol($uri), $flags]
                );
            }

            // Any other response from the indexed resolver (for example 'entry_not_found')
            // indicates an index exists but the exact entry may be missing — treat as existing directory.
            self::$logger->log("Assuming directory exists (index present): $uri");
            return [
                'dev'     => 0,
                'ino'     => 0,
                'mode'    => 0040000,
                'nlink'   => 1,
                'uid'     => 0,
                'gid'     => 0,
                'rdev'    => 0,
                'size'    => 0,
                'atime'   => time(),
                'mtime'   => time(),
                'ctime'   => time(),
                'blksize' => -1,
                'blocks'  => -1,
            ];
        }

        // File check: require an exact entry in the index; if missing, return false; otherwise delegate.
        try {
            $response = self::$streamWrapperIndexed->url_stat($uri, $flags);
        } catch (\Throwable $e) {
            self::$logger->log("url_stat (file) failed: " . $e->getMessage());
        }

        if (is_array($response)) {
            return $response;
        }

        if ($response === 'entry_not_found') {
            return false;
        }

        return $this->__call(
            'url_stat',
            [self::$pathParser->normalizePathWithProtocol($uri), $flags]
        );
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