<?php 

namespace S3_Local_Index\Index\Exception;

class IndexNotFoundException extends IndexManagerException
{
    public function __construct(string $path)
    {
        parent::__construct(
            id: 'not_found',
            message: "Index not found for path: {$path}"
        );
    }
}
