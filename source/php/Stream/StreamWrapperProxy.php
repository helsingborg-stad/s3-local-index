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
    public $context;

    private static array $streamWrapperResolvers = [];
    private static StreamWrapperInterface    $streamWrapperOriginal;
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
        $uri            = self::$pathParser->normalizePath($uri);

        foreach(self::$streamWrapperResolvers as $resolver) {

            // Check if this resolver can handle the request
            if ($resolver->canResolve($uri, $flags)) {
                // Resolve returns: 
                // false: if not found
                // array: if found (array is faked stat data) 
                // null: if unable to determine (try next resolver or original)
                $response = $resolver->url_stat($uri, $flags);

                if(!is_null($response)) {
                    return $response;
                }
            }
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
            self::$logger->log("Delegating $name to original stream wrapper. Args: " . json_encode($args));
            return self::$streamWrapperOriginal->$name(...$args);
        }
        throw new \BadMethodCallException("Method $name not found on delegate");
    }
}