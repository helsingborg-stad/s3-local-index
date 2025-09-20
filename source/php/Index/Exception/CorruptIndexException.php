<?php 

namespace S3_Local_Index\Index\Exception;

class CorruptIndexException extends IndexManagerException
{
    public function __construct(string $path)
    {
        parent::__construct(
            id: 'corrupt_index',
            message: "Corrupt Index. Could not parse index at: {$path}"
        );
    }
}
