<?php

namespace S3_Local_Index\Stream;

use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\FileSystem\FileSystemInterface;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Parser\PathParserInterface;
use S3_Local_Index\Index\IndexManager;

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
     * @param PathParserInterface $pathParser     Parser interface for path operations
     */
    public function __construct(
        private CacheInterface $cache,
        private FileSystemInterface $fileSystem,
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
        try {
            $index = $this->indexManager->read($path);
        } catch (\S3_Local_Index\Exception\IndexException $e) {
            switch ($e->getId()) {
                case 'index_not_found':
                    $this->logger->log("Index missing: {$e->getMessage()}");
                    return $e-getId();
                    break;

                case 'index_corrupt':
                    $this->logger->log("Index corrupt, needs rebuild: {$e->getMessage()}");
                    break;

                case 'entry_invalid_path':
                    $this->logger->log("Could not resolve path to something useful: {$e->getMessage()}");
                    break;

                default:
                    $this->logger->log("Unknown index error [{$e->getId()}]: {$e->getMessage()}");
                    break;
            }
        }

        //If not found, flag as unavabile.
        if (in_array($this->pathParser->normalizePath($path), $index, true) === false) {
            $this->logger->log("Entry not found: " . $path);
            return 'entry_not_found';
        }

        //Message file found
        $this->logger->log("Entry found: " . $path);

        //Resolve as found. 
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