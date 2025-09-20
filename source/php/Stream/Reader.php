<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\Logger\LoggerInterface;
use S3LocalIndex\Parser\ParserInterface;

/**
 * Stream reader for S3 files with local index support.
 * 
 * This class provides stream operations for S3 files using a local index
 * for fast file existence checks and metadata operations. It supports both
 * single-site and multisite WordPress configurations.
 */
class Reader implements ReaderInterface
{
    /**
     * Constructor with dependency injection
     *
     * @param CacheInterface      $cache      Cache interface for storing index data
     * @param FileSystemInterface $fileSystem File system interface for accessing index files
     * @param LoggerInterface     $logger     Logger interface for debugging messages
     * @param ParserInterface     $parser     Parser interface for path operations
     */
    public function __construct(
        private CacheInterface $cache,
        private FileSystemInterface $fileSystem,
        private LoggerInterface $logger,
        private ParserInterface $parser
    ) {
    }

    /**
     * Flush cache for a specific file path
     *
     * @param  string $path S3 file path
     * @return bool True if cache was flushed, false if path doesn't match pattern
     */
    public function flushCacheForPath(string $path): bool
    {
        $details = $this->parser->getPathDetails($path);
        if ($details === null) {
            return false;
        }

        $cacheKey = $this->parser->createCacheIdentifier($details);
        
        return $this->cache->delete($cacheKey);
    }

    

    /**
     * Get file statistics.
     * 
     * Implementation of PHP's url_stat for the stream wrapper.
     * Returns basic file stats if the file exists in the index.
     * 
     * @param  string $path  Path to stat
     * @param  int    $flags Stat flags
     * @return array|false File statistics or false if file doesn't exist
     */
    public function url_stat(string $path, int $flags) : string|array
    {
        $normalized = $this->parser->normalizePath($path);
        $index      = $this->loadIndex($normalized);

        if(empty($index)) {
            $this->logger->log("Index cannot be found.");
        } else {
            $this->logger->log("");
            $this->logger->log("");
            $this->logger->log("Path: {$path}");
            $this->logger->log("Norm: {$normalized} ");
            $this->logger->log("Example: " . $index[count($index)-1] ?? 'none');
            $this->logger->log("");
            $this->logger->log("");
        }

        if (empty($index)) {
            return 'no_index';
        }

        //Check if value exists in index
        if (in_array($normalized, $index, true) === false) {
            return 'not_found';
        }

        return [
            0 => 0,    // dev
            1 => 0,    // ino
            2 => 0100644, // mode (regular file, 644 perms)
            7 => 0,    // size
            9 => time(), // mtime

            'dev'   => 0,
            'ino'   => 0,
            'mode'  => 0100644,
            'nlink' => 1,
            'uid'   => 0,
            'gid'   => 0,
            'rdev'  => 0,
            'size'  => 0,
            'atime' => time(),
            'mtime' => time(),
            'ctime' => time(),
        ];
    }
}