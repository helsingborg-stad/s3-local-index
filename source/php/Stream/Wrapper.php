<?php
declare(strict_types=1);

namespace S3_Local_Index\Stream;

use S3_Local_Index\Stream\ReaderInterface;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Stream\WrapperInterface;
use S3_Local_Index\Parser\PathParserInterface;

class Wrapper implements WrapperInterface
{
    private static ReaderInterface     $reader;
    private static PathParserInterface $pathParser;
    private static LoggerInterface     $logger;

    private const PROTOCOL = 's3';

    public $context;

    private static WrapperInterface $delegate;
    private static bool $registered = false;

    private ?string $currentPath = null;

    public function __construct() {}

    /**
     * Set dependencies statically.
     */
    public static function setDependencies(
        ReaderInterface $reader,
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
        if (self::$registered === true) {
            return;
        }

        $protocol = self::PROTOCOL;
        if (in_array($protocol, stream_get_wrappers(), true)) {
            stream_wrapper_unregister($protocol);
        }
        self::$registered = stream_wrapper_register($protocol, static::class, STREAM_IS_URL);

        if (self::$registered) {
            self::$logger->log("Stream wrapper for {$protocol} successfully registered.");
        } else {
            self::$logger->log("Failed to register stream wrapper for {$protocol}.");
        }
    }

    /**
     * @inheritDoc
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path): bool
    {
        $this->currentPath = $path;

        $result = $this->makeDelegation('stream_open', [
            $path,
            $mode,
            $options,
            $opened_path
        ]);

        if (!$result) {
            $this->currentPath = null;
        }
        return (bool) $result;
    }

    public function stream_close(): void
    {
        if ($this->currentPath !== null) {
            self::$reader->stream_close();
        }
        $this->makeDelegation('stream_close', []);
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
        $isFileExists   = $this->isFileExistsQuery($uri, $flags);

        //Should not be handled by us, delegate
        if (!$isFileExists) {
            self::$logger->log("Delegating url_stat for non-file_exists query: $uri");
            return $this->makeDelegation(
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
                default                         => $this->makeDelegation(
                    'url_stat',
                    [self::$pathParser->normalizePathWithProtocol($uri), $flags]
                ),
            };
        }
    }

    /**
     * @inheritDoc
     */
    public function stream_flush(): bool
    {
        $result = (bool) $this->makeDelegation('stream_flush', []);
        if ($result) {
            try {
                self::$reader->context = $this->context;
                self::$reader->stream_flush();
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
        $result = (bool) $this->makeDelegation('unlink', [$path]);
        if ($result) {
            try {
                self::$reader->unlink($path);
            } catch (\Throwable $e) {
                self::$logger->log("Failed removing from index: " . $e->getMessage());
            }
        }
        return $result;
    }

    /**
     * Reusable make delegation function.
     *
     * @param string $name Method name
     * @param array<int,mixed> $args Arguments
     * @return mixed
     */
    private function makeDelegation(string $name, array $args): mixed
    {
        if (method_exists(self::$delegate, $name)) {
            self::$delegate->context = $this->context;
            return self::$delegate->$name(...$args);
        }
        throw new \BadMethodCallException("Method $name not found on delegate");
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
        return $this->makeDelegation($name, $args);
    }

    /**
     * Determine if the url_stat call is a file_exists check.
     *
     * @param string $uri The URI being checked.
     * @param int $flags The flags passed to url_stat.
     * @return bool True if it's a file_exists check, false otherwise.
     */
    private function isFileExistsQuery($uri, int $flags): bool
    {
        $isFile   = pathinfo($uri, PATHINFO_EXTENSION) !== ''; // true if it's a file_exists/is_file/is_dir check
        $isExists = ($flags & STREAM_URL_STAT_QUIET) !== 0; // true if it's a file_exists check
        return $isFile && $isExists;
    }
}