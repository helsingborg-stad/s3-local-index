<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Config\Config;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\Stream\StreamWrapperInterface;

/**
 * Cached Stream Wrapper
 *
 * Wraps a StreamWrapperInterface and caches results from url_stat
 * using the provided CacheInterface.
 */
class StreamWrapperCached implements StreamWrapperInterface
{
    use \S3_Local_Index\Stream\Response\ResponseTrait;

    public $context;

    private StreamWrapperInterface $delegate;
    private LoggerInterface $logger;
    private CacheInterface $cache;
    private Config $config;

    private string $cacheGroup = 's3_stream_stat_cache';
    private int $ttl;

    public function __construct(
        StreamWrapperInterface $delegate,
        LoggerInterface $logger,
        CacheInterface $cache,
        Config $config
    ) {
        $this->delegate = $delegate;
        $this->logger   = $logger;
        $this->cache    = $cache;
        $this->config   = $config;
        $this->ttl      = $this->config->getCacheTtl();

        $this->logger->log(sprintf(
            '[StreamWrapperCached] Initialized with TTL=%d, cache group=%s',
            $this->ttl,
            $this->cacheGroup
        ));
    }

    /**
     * Cached url_stat implementation.
     */
    public function url_stat(string $uri, int $flags): array|false
    {
        if (!$this->config->isCacheEnabled()) {
            $this->logger->log("[StreamWrapperCached] Cache disabled — delegating directly for: {$uri}");
            return $this->delegate->url_stat($uri, $flags);
        }

        $cacheKey = $this->cacheKey($uri);

        if ($this->cache->has($cacheKey)) {
            $cached = $this->cache->get($cacheKey);

            if ($cached === 'found') {
                $this->logger->log("[StreamWrapperCached] Cache hit (found) for: {$uri}");
                return ['cached' => true];
            }

            if ($cached === 'notfound') {
                $this->logger->log("[StreamWrapperCached] Cache hit (notfound) for: {$uri}");
                return false;
            }

            if (is_array($cached)) {
                $this->logger->log("[StreamWrapperCached] Cache hit (array) for: {$uri}");
                return $cached;
            }
        }

        // Cache miss
        $this->logger->log("[StreamWrapperCached] Cache miss for: {$uri}, delegating to original");
        $result = $this->delegate->url_stat($uri, $flags);

        // Cache only existence results
        if (is_array($result)) {
            $this->cache->set($cacheKey, 'found', $this->ttl);
        } elseif ($result === false) {
            $this->cache->set($cacheKey, 'notfound', $this->ttl);
        }

        return $result;
    }

    /**
     * Invalidate cache for this URI.
     */
    private function invalidate(string $uri): void
    {
        $cacheKey = $this->cacheKey($uri);
        $this->cache->delete($cacheKey);
        $this->logger->log("[StreamWrapperCached] Cache invalidated for: {$uri}");
    }

    /**
     * Build a consistent cache key.
     */
    private function cacheKey(string $uri): string
    {
        // Create a stable hash for uniqueness.
        return md5($this->cacheGroup . ':' . $uri);
    }

    // ---- File operation hooks ----

    public function unlink(string $uri): bool
    {
        $this->invalidate($uri);
        return $this->delegate->unlink($uri);
    }

    public function rename(string $from, string $to): bool
    {
        $this->invalidate($from);
        $this->invalidate($to);
        return $this->delegate->rename($from, $to);
    }

    public function mkdir(string $uri, int $mode, int $options): bool
    {
        $this->invalidate($uri);
        return $this->delegate->mkdir($uri, $mode, $options);
    }

    public function rmdir(string $uri, int $options): bool
    {
        $this->invalidate($uri);
        return $this->delegate->rmdir($uri, $options);
    }

    public function stream_write(string $data): int|false
    {
        if (isset($this->currentUri)) {
            $this->invalidate($this->currentUri);
        }
        return $this->delegate->stream_write($data);
    }

    /**
     * Proxy all other stream operations to the delegate.
     */
    public function __call(string $name, array $args): mixed
    {
        if (method_exists($this->delegate, $name)) {
            $this->delegate->context = $this->context;
            return $this->delegate->$name(...$args);
        }

        throw new \BadMethodCallException("Unknown stream wrapper method: {$name}");
    }
}