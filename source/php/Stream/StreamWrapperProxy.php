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

        foreach(self::$resolvers as $resolver) {
            if ($resolver->canResolve($uri, $flags)) {
                $response = $resolver->url_stat($uri, $flags);
                if(is_array($response) || $response === false) {
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
            return self::$streamWrapperOriginal->$name(...$args);
        }
        throw new \BadMethodCallException("Method $name not found on delegate");
    }
}