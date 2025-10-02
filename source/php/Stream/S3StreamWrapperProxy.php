<?php
declare(strict_types=1);

namespace S3_Local_Index\Stream;

use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Parser\PathParserInterface;

/**
 * S3 Stream Wrapper Proxy with local index optimization.
 * 
 * This proxy intercepts S3 stream operations and uses a chain of resolvers
 * to optimize file existence checks using local indexes before falling back
 * to the original S3 stream wrapper for actual file operations.
 * 
 * The proxy implements the Chain of Responsibility pattern to handle
 * different types of requests with appropriate resolvers.
 */
class S3StreamWrapperProxy implements WrapperInterface
{
    private StreamResolverChain $resolverChain;
    private PathParserInterface $pathParser;
    private LoggerInterface $logger;
    private WrapperInterface $originalWrapper;

    public $context;

    public function __construct() {}

    /**
     * Set dependencies for the stream wrapper proxy.
     */
    public function setDependencies(
        StreamResolverChain $resolverChain,
        PathParserInterface $pathParser,
        LoggerInterface $logger,
        WrapperInterface $originalWrapper
    ): void {
        $this->resolverChain = $resolverChain;
        $this->pathParser = $pathParser;
        $this->logger = $logger;
        $this->originalWrapper = $originalWrapper;
    }

    /**
     * File exists check handler with resolver chain.
     * 
     * @inheritDoc
     */
    public function url_stat(string $uri, int $flags): array|false
    {
        $normalizedUri = $this->pathParser->normalizePath($uri);
        
        // Try resolver chain first
        if ($this->resolverChain->canResolve($normalizedUri, $flags)) {
            try {
                $response = $this->resolverChain->resolve($normalizedUri, $flags);
                
                return match (true) {
                    is_array($response) => $response,
                    $response === 'entry_not_found' => false,
                    default => $this->delegateToOriginalWrapper('url_stat', [$uri, $flags])
                };
            } catch (\Throwable $e) {
                $this->logger->log("Resolver chain failed: " . $e->getMessage());
            }
        }

        // Delegate to original wrapper if no resolver can handle it
        $this->logger->log("Delegating url_stat to original wrapper: $uri");
        return $this->delegateToOriginalWrapper('url_stat', [$uri, $flags]);
    }

    /**
     * Delegate method calls to the original stream wrapper.
     *
     * @param string $methodName Method name
     * @param array<int,mixed> $arguments Arguments
     * @return mixed
     */
    private function delegateToOriginalWrapper(string $methodName, array $arguments): mixed
    {
        // Normalize path with protocol for original wrapper
        if ($methodName === 'url_stat' && isset($arguments[0])) {
            $arguments[0] = $this->pathParser->normalizePathWithProtocol($arguments[0]);
        }

        if (method_exists($this->originalWrapper, $methodName)) {
            if (is_resource($this->context)) {
                $this->originalWrapper->context = $this->context;
            }
            return $this->originalWrapper->$methodName(...$arguments);
        }

        throw new \BadMethodCallException("Method $methodName not found on original wrapper");
    }

    /**
     * Magic method to delegate all other calls to the original stream wrapper.
     *
     * @param string $name Method name
     * @param array<int,mixed> $args Arguments
     * @return mixed
     */
    public function __call(string $name, array $args): mixed
    {
        return $this->delegateToOriginalWrapper($name, $args);
    }
}