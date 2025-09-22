<?php 

namespace S3_Local_Index\Index\Exception;

class IndexCorruptException extends IndexManagerException
{
    public function __construct(string $path)
    {
        parent::__construct(
            id: 'index_corrupt',
            message: "Corrupt Index. Could not parse index at: {$path}"
        );
    }
}
