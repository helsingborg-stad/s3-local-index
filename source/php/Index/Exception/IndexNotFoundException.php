<?php 

namespace S3_Local_Index\Index\Exception;

/**
 * Exception thrown when an index is not found for a given path.
 */
class IndexNotFoundException extends IndexManagerException
{
    /** 
     * Constructor for IndexNotFoundException.
     *
     * @param string $path The path for which the index was not found.
     */
    public function __construct(string $path)
    {
        parent::__construct(
            id: 'index_not_found',
            message: "Index not found for path: {$path}"
        );
    }
}
