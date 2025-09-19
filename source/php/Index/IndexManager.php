<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\Logger\LoggerInterface;
use S3LocalIndex\Parser\ParserInterface;

/**
 * Handles filesystem index operations. 
 */
class IndexManager implements IndexManagerInterface
{
    public function __construct(
        private CacheInterface $cache,
        private FileSystemInterface $fileSystem,
        private LoggerInterface $logger,
        private ParserInterface $parser
    ) {
    }

    /*
     * @inheritDoc
     */
    public function read(string $path): array
    {
        //Get path details
        $details = $this->parser->getPathDetails($path);
        if ($details === null) {
            throw new InvalidArgumentException("Invalid path provided: {$path}");
        }

        //Get cache key & check in cache
        $cacheKey   = $this->parser->createCacheIdentifier($details);
        $cachedData = $this->cache->get($cacheKey);

        //Abort, return cache 
        if ($cachedData !== null) {
            return $cachedData;
        }

        $file = $this->fileSystem->getCacheFilePath($details);

        $this->logger->log("Loading index from file: {$file}");

        if (!$this->fileSystem->fileExists($file)) {
            throw new Exception("Index does not exist: {$file}");
        }

        $data  = $this->fileSystem->fileGetContents($file);
        $index = json_decode($data, true) ?: [];

        $this->cache->set($cacheKey, $index, 3600);

        return $index;
    }

    /*
     * @inheritDoc
     */
    public function write(string $path): bool
    {
        $details = $this->parser->getPathDetails($path);
        if ($details === null) {
            return false;
        }

        $cacheKey   = $this->parser->createCacheIdentifier($details);
        $file       = $this->fileSystem->getCacheDir() . "/" . $this->fileSystem->getCacheFileName($details);
        $index      = $this->loadIndex($path);
        $normalized = $this->parser->normalizePath($path);

        $index[$normalized] = true;

        $this->fileSystem->filePutContents($file, json_encode($index));
        $this->cache->set($cacheKey, $index, 3600);

        return true;
    }

    /*
     * @inheritDoc
     */
    public function delete(string $path): bool
    {
        $details = $this->parser->getPathDetails($path);
        if ($details === null) {
            return false;
        }

        $cacheKey   = $this->parser->createCacheIdentifier($details);
        $file       = $this->fileSystem->getCacheDir() . "/" . $this->fileSystem->getCacheFileName($details);
        $index      = $this->loadIndex($path);
        $normalized = $this->parser->normalizePath($path);

        unset($index[$normalized]);

        $this->fileSystem->filePutContents($file, json_encode($index));
        $this->cache->set($cacheKey, $index, 3600);

        return true;
    }
}