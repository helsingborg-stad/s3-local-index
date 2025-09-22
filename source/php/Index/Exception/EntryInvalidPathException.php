<?php 

namespace S3_Local_Index\Index\Exception;

class EntryInvalidPathException extends IndexManagerException
{
    public function __construct(string $path)
    {
        parent::__construct(
            id: 'entry_invalid_path',
            message: "Invalid Path. Could not parse path: {$path}"
        );
    }
}
