<?php
declare(strict_types=1);

namespace S3_Local_Index\Stream;

use S3_Local_Index\Config\Config;
use S3_Local_Index\Logger\Logger;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Stream\StreamWrapperInterface;
use S3_Local_Index\Parser\PathParserInterface;
use WpService\Implementations\NativeWpService;

class StreamWrapperProxy implements StreamWrapperInterface
{
    private ?StreamWrapperInterface $delegate = null;

    public $context;

    private static array $streamWrapperResolvers = [];
    private static StreamWrapperInterface $streamWrapperOriginal;
    private static PathParserInterface $pathParser;
    private static LoggerInterface $logger;

    /**
     * Set dependencies statically.
     */
    public static function setDependencies(
        LoggerInterface $logger,
        PathParserInterface $pathParser,
        StreamWrapperInterface $streamWrapperOriginal,
        StreamWrapperResolverInterface ...$streamWrapperResolvers
    ): void {
        self::$logger = $logger;
        self::$pathParser = $pathParser;
        self::$streamWrapperOriginal   = $streamWrapperOriginal;
        self::$streamWrapperResolvers    = $streamWrapperResolvers;
    }

    /**
     * Proxy for url_stat calls to handle with resolvers first.
     * If no resolver can handle, delegate to original stream wrapper.
     * 
     * @inheritDoc
     */
    public function url_stat(string $uri, int $flags): array|false
    {
        $response       = null;
        $rawUri         = $uri;
        $uri            = self::$pathParser->normalizePath($uri);

        foreach (self::$streamWrapperResolvers as $resolver) {

            // Check if this resolver can handle the request
            if ($resolver->canResolve($uri, $flags)) {
                // Resolve returns: 
                // false: if not found
                // array: if found (array is faked stat data) 
                // null: if unable to determine (try next resolver or original)
                $response = $resolver->url_stat($uri, $flags);

                if (!is_null($response)) {
                    return $response;
                }
            }
        }

        return $this->__call(
            'url_stat',
            [$rawUri, $flags]
        );
    }

    /**
     * Open a stream.
     * 
     * Implementation of PHP's stream_open for the stream wrapper.
     * If the mode is for writing, it checks if the file exists first
     * using url_stat to prevent creating new files.
     * 
     * @param  string      $uri         The URI to open
     * @param  string      $mode        The mode to open the stream in
     * @param  int         $options     Stream options
     * @param  string|null $opened_path If the path was opened, this will be set to that path
     * 
     * @return bool True on success, false on failure
     */
    public function stream_open(string $uri, string $mode, int $options, ?string &$opened_path): bool
    {
        $requiresExistingFile = in_array($mode, ['r', 'r+', 'rb', 'rb+'], true);
        if ($requiresExistingFile && !$this->url_stat($uri, 0)) {
            return false;
        }
        return $this->__call(
            'stream_open',
            [$uri, $mode, $options, &$opened_path]
        );
    }

    /**
     * Magic method to delegate calls to the original stream wrapper.
     * The original stream wrapper is cloned on first use to avoid
     * side effects. The original stream wrapper needs to be stateful
     * to be able to remember its injected dependencies.
     *
     * @param string $name Method name
     * @param array<int,mixed> $args Arguments
     * @return mixed
     */
    public function __call(string $name, array $args): mixed
    {
        if (!isset($this->delegate)) {
            $this->delegate = clone self::$streamWrapperOriginal;
        }

        $truncatedArgs = array_map(function ($arg) {
            if (is_string($arg) && strlen($arg) > 100) {
                return substr($arg, 0, 100) . '...';
            }
            return $arg;
        }, $args);

        self::$logger->log(
            "Delegating {$name} to original stream wrapper. Args: " . json_encode($truncatedArgs)
        );

        $this->delegate->context = $this->context;

        try {
            return $this->delegate->$name(...$args);
        } catch (\Error $e) {
            throw new \BadMethodCallException("Method {$name} not found on delegate", 0, $e);
        }
    }
}