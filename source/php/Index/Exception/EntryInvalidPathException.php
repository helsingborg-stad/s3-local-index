<?php 

namespace S3_Local_Index\Index\Exception;

class EntryInvalidPathException extends IndexManagerException
{
    /** 
     * Constructor for EntryInvalidPathException.
     *
     * @param string $path The invalid path that could not be parsed.
     */
    public function __construct(string $path)
    {
        parent::__construct(
            id: 'entry_invalid_path',
            message: "Invalid Path. Could not parse path: {$path}"
        );
    }
}
