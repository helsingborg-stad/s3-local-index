<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Logger\LoggerInterface;

/**
 * Chain of responsibility for stream resolvers.
 * 
 * This class manages multiple stream resolvers and delegates
 * requests to the appropriate resolver based on their ability
 * to handle the specific request.
 */
class StreamResolverChain implements StreamResolverInterface
{
    /** @var StreamResolverInterface[] */
    private array $resolvers = [];

    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    /**
     * Add a resolver to the chain.
     *
     * @param StreamResolverInterface $resolver The resolver to add
     * @return self For method chaining
     */
    public function addResolver(StreamResolverInterface $resolver): self
    {
        $this->resolvers[] = $resolver;
        return $this;
    }

    /**
     * Check if any resolver in the chain can handle the request.
     *
     * @param string $uri   The URI to check
     * @param int    $flags The flags for the stat operation
     * @return bool True if any resolver can handle the request
     */
    public function canResolve(string $uri, int $flags): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->canResolve($uri, $flags)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolve using the first capable resolver in the chain.
     *
     * @param string $uri   The URI to resolve
     * @param int    $flags The flags for the stat operation
     * @return array|false|string File statistics, false if not found, or string for special cases
     */
    public function resolve(string $uri, int $flags): array|false|string
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->canResolve($uri, $flags)) {
                $this->logger->log("Using resolver: " . get_class($resolver) . " for URI: {$uri}");
                return $resolver->resolve($uri, $flags);
            }
        }

        $this->logger->log("No resolver found for URI: {$uri}");
        return false;
    }

    /**
     * Get all registered resolvers.
     *
     * @return StreamResolverInterface[]
     */
    public function getResolvers(): array
    {
        return $this->resolvers;
    }
}