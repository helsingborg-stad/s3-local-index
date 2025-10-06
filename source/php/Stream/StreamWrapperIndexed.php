<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Parser\PathParserInterface;
use S3_Local_Index\Index\IndexManager;
use S3_Local_Index\Index\Exception\IndexManagerException;

/**
 * Stream reader for S3 files with local index support.
 * 
 * This class provides stream operations for S3 files using a local index
 * for fast file existence checks and metadata operations. It supports both
 * single-site and multisite WordPress configurations.
 */
class StreamWrapperIndexed implements StreamWrapperInterface
{
    public $context;

    /**
     * Constructor with dependency injection
     *
     * @param CacheInterface      $cache        Cache interface for storing index data
     * @param LoggerInterface     $logger       Logger interface for debugging messages
     * @param PathParserInterface $pathParser   Parser interface for path operations
     * @param IndexManager        $indexManager Index manager for handling local index
     */
    public function __construct(
        private CacheInterface $cache,
        private LoggerInterface $logger,
        private PathParserInterface $pathParser,
        private IndexManager $indexManager
    ) {
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
        $normalized = $this->pathParser->normalizePath($path);
        $isDir = pathinfo($normalized, PATHINFO_EXTENSION) === '';

        try {
            $index = $this->indexManager->read($path);
        } catch (IndexManagerException $e) {
            switch ($e->getId()) {
                case 'index_not_found':
                    $this->logger->log("Index missing: {$e->getMessage()}");
                    return $e->getId();

                case 'index_corrupt':
                    $this->logger->log("Index corrupt, needs rebuild: {$e->getMessage()}");
                    break;

                case 'entry_invalid_path':
                    $this->logger->log("Could not resolve path to index: {$e->getMessage()}");
                    break;
            }

            // if we could not read the index for reasons other than missing index,
            // continue with an empty index so subsequent checks behave predictably.
            $index = [];
        }

        // If this is a directory check and we managed to read an index (i.e. we didn't return
        // 'index_not_found' above) we assume the directory exists.
        if ($isDir) {
            $this->logger->log("Directory index present, treating as existing directory: {$path}");
            return [
                'dev'     => 0,
                'ino'     => 0,
                'mode'    => 0040000,
                'nlink'   => 1,
                'uid'     => 0,
                'gid'     => 0,
                'rdev'    => 0,
                'size'    => 0,
                'atime'   => time(),
                'mtime'   => time(),
                'ctime'   => time(),
                'blksize' => -1,
                'blocks'  => -1,
            ];
        }

        // For file checks, we require the exact entry to be present in the index.
        if (in_array($normalized, $index, true) === false) {
            $this->logger->log("Entry not found: " . $path);
            return 'entry_not_found';
        }

        // File found
        $this->logger->log("Entry found: " . $path);

        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => 0100000,
            'nlink'   => 1,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => 0,
            'atime'   => time(),
            'mtime'   => time(),
            'ctime'   => time(),
            'blksize' => -1,
            'blocks'  => -1,
        ];
    }
}