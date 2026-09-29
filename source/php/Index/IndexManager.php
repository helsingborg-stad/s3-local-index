<?php

namespace S3_Local_Index\Index;

use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Parser\PathParserInterface;
use S3_Local_Index\Index\IndexManagerInterface;

use S3_Local_Index\Index\Exception\IndexNotFoundException; 
use S3_Local_Index\Index\Exception\InvalidPathException;
use S3_Local_Index\Index\Exception\IndexCorruptException;
use S3_Local_Index\Index\Exception\EntryInvalidPathException;
use S3_Local_Index\Index\Exception\CannotWriteToIndex;

/**
 * Handles filesystem index operations. 
 */
class IndexManager implements IndexManagerInterface
{
    public function __construct(
        private CacheInterface $cache,
        private FileSystemInterface $fileSystem,
        private LoggerInterface $logger,
        private PathParserInterface $pathParser
    ) {
    }

    /*
     * @inheritDoc
     */
    public function read(string $path): array
    {
        //Early bailout
        $details = $this->pathParser->getPathDetails($path);
        if ($details === null) {
            throw new EntryInvalidPathException($path);
        }

        //Return cached response if exists. 
        $cacheKey   = $this->cache->createCacheIdentifier($details);
        $cachedData = $this->cache->get($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }

        //Load from index file
        $file = $this->fileSystem->getCacheFilePath($details);
        if (!$this->fileSystem->fileExists($file)) {
            throw new IndexNotFoundException($file);
        }

        //Read data
        $data  = $this->fileSystem->fileGetContents($file);
        $index = json_decode($data, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($index)) {
            throw new IndexCorruptException($file);
        }

        //Set in cache
        $this->cache->set($cacheKey, $index, 3600);

        //Return
        return $index;
    }

    /*
     * @inheritDoc
     */
    /*
    * @inheritDoc
    */
    public function write(string $path, array $metaData = []): bool
    {
        // Early bailout
        $details = $this->pathParser->getPathDetails($path);
        if ($details === null) {
            throw new EntryInvalidPathException($path);
        }

        $cacheKey   = $this->cache->createCacheIdentifier($details);
        $file       = $this->fileSystem->getCacheFilePath($details);
        $normalized = $this->pathParser->normalizePath($path);

        try {
            $this->fileSystem->mutateIndexFile($file, static function (array $index) use ($normalized, $metaData): array {
                $index[$normalized] = $metaData;
                return $index;
            });
        } catch (\Throwable $e) {
            $this->logger->error("Failed to add {$normalized} to index {$file}: {$e->getMessage()}");
            throw new CannotWriteToIndex($file, $e);
        }

        // A different process may have changed the file since this request cached it.
        $this->cache->delete($cacheKey);

        return true;
    }

    /*
    * @inheritDoc
    */
    public function delete(string $path): bool
    {
        // Early bailout
        $details = $this->pathParser->getPathDetails($path);
        if ($details === null) {
            throw new EntryInvalidPathException($path);
        }

        $cacheKey   = $this->cache->createCacheIdentifier($details);
        $file       = $this->fileSystem->getCacheFilePath($details);
        $normalized = $this->pathParser->normalizePath($path);

        try {
            $this->fileSystem->mutateIndexFile($file, static function (array $index) use ($normalized): array|false {
                if (!array_key_exists($normalized, $index)) {
                    return false;
                }
                unset($index[$normalized]);
                return $index;
            });
        } catch (\Throwable $e) {
            $this->logger->error("Failed to remove {$normalized} from index {$file}: {$e->getMessage()}");
            throw new CannotWriteToIndex($file, $e);
        }

        $this->cache->delete($cacheKey);

        return true;
    }
}
