<?php 

namespace S3_Local_Index\Index\Exception;

/**
 * Exception thrown when an index cannot be written for a given path.
 */
class CannotWriteToIndex extends IndexManagerException
{
    /** 
     * Constructor for CannotWriteToIndex.
     *
     * @param string $path The path for which the index could not be written.
     */
    public function __construct(string $path)
    {
        parent::__construct(
            id: 'cannot_write_to_index',
            message: "Cannot write index: {$path}"
        );
    }
}
