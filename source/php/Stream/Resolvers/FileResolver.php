<?php

namespace S3_Local_Index\Stream\Resolvers;

use S3_Local_Index\Cache\CacheInterface;
use S3_Local_Index\Logger\LoggerInterface;
use S3_Local_Index\Parser\PathParserInterface;
use S3_Local_Index\Index\IndexManager;
use S3_Local_Index\Index\Exception\IndexManagerException;
use S3_Local_Index\Stream\StreamWrapperResolverInterface;
use WpService\WpService;
use S3_Local_Index\Stream\Response\ResponseTrait;

/**
 * Stream reader for S3 files with local index support.
 * 
 * This class provides stream operations for S3 files using a local index
 * for fast file existence checks and metadata operations. It supports both
 * single-site and multisite WordPress configurations.
 */
class FileResolver implements StreamWrapperResolverInterface
{
    public $context;

    use ResponseTrait;

    /**
     * Constructor with dependency injection
     *
     * @param LoggerInterface     $logger       Logger interface for debugging messages
     * @param PathParserInterface $pathParser   Parser interface for path operations
     * @param IndexManager        $indexManager Index manager for handling local index
     */
    public function __construct(
        private WpService $wpService,
        private LoggerInterface $logger,
        private PathParserInterface $pathParser,
        private IndexManager $indexManager
    ) {
    }

    /**
     * Determine if this resolver can handle the given path and flags.
     * 
     * This implementation checks if the path has a file extension and
     * if the STREAM_URL_STAT_QUIET flag is set, indicating a file existence
     * check.
     * 
     * @param  string $path  The path to check
     * @param  int    $flags The flags for the stat operation
     * @return bool   True if this resolver can handle the request, false otherwise
     */
    public function canResolve(string $path, int $flags): bool
    {
        return pathinfo($path, PATHINFO_EXTENSION) !== ''
            && ($flags & STREAM_URL_STAT_QUIET) !== 0;
    }

    /**
     * Get the unique identifier for this resolver.
     * 
     * @return string The resolver ID
     */
    public function resolverId(): string
    {
        return 'file';
    }

    /**
     * Get file statistics.
     * 
     * Implementation of PHP's url_stat for the stream wrapper.
     * Returns basic file stats if the file exists in the index.
     * 
     * @param  string $path  Path to stat
     * @param  int    $flags Stat flags
     * 
     * @return null|array|false Null if unable to determine, false if not found, or
     *                          an array of file statistics if found.
     */
    public function url_stat(string $path, int $flags) : null|false|array
    {
        try {
            $index = $this->indexManager->read($path);
        } catch (IndexManagerException $e) {
            switch ($e->getId()) {
                case 'index_not_found':
                    $this->logger->log("Index missing: {$e->getMessage()}");
                    return $this->url_stat_response()->bypass();
                    break;

                case 'index_corrupt':
                    $this->logger->log("Index corrupt, needs rebuild: {$e->getMessage()}");
                    return $this->url_stat_response()->bypass();
                    break;

                case 'entry_invalid_path':
                    $this->logger->log("Could not resolve path to index: {$e->getMessage()}");
                    return $this->url_stat_response()->bypass();
                    break;
            }
        }

        //If not found, flag as unavabile.
        if (in_array($this->pathParser->normalizePath($path), $index, true) === false) {
            $this->logger->log("Entry not found: " . $path);
            return $this->url_stat_response()->notfound();
        }

        //Message file found
        $this->logger->log("Entry found: " . $path);

        //Resolve as found. 
        return $this->url_stat_response()->found('file');
    }
}