<?php

namespace S3_Local_Index\Index;

use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Parser\PathParserInterface;
use S3_Local_Index\Index\IndexManagerInterface;

use S3_Local_Index\Index\Exception\IndexNotFoundException; 
use S3_Local_Index\Index\Exception\InvalidPathException;
use S3_Local_Index\Index\Exception\CorruptIndexException;

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
            throw new EntryInvalidPathException();
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
            throw new IndexNotFoundException();
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
    public function write(string $path): bool
    {
        //Early bailout
        $details = $this->pathParser->getPathDetails($path);
        if ($details === null) {
            throw new InvalidPathException();
        }

        //Get existing data from index file
        $index = $this->read($path); 

        //Prepare paths, keys & data
        $cacheKey   = $this->cache->createCacheIdentifier($details);
        $file       = $this->fileSystem->getCacheFilePath($details);
        $normalized = $this->pathParser->normalizePath($path);

        //Append to index
        $index[] = $normalized;
        $this->fileSystem->filePutContents($file, json_encode($index));

        //Update cache
        $this->cache->set($cacheKey, $index, 3600);

        return true;
    }

    /*
     * @inheritDoc
     */
    public function delete(string $path): bool
    {
        //Early bailout
        $details = $this->pathParser->getPathDetails($path);
        if ($details === null) {
            throw new InvalidPathException();
        }

        //Get existing data from index file
        $index = $this->read($path); 

        //Prepare paths, keys & data
        $cacheKey   = $this->cache->createCacheIdentifier($details);
        $file       = $this->fileSystem->getCacheFilePath($details);
        $normalized = $this->pathParser->normalizePath($path);

        //Evict from index
        $keys = array_keys($index, $normalized, true);
        foreach($keys as $key) {
            unset($index[$key]);
        }

        //Write to index & cache
        $this->fileSystem->filePutContents($file, json_encode($index));
        $this->cache->set($cacheKey, $index, 3600);

        return true;
    }
}