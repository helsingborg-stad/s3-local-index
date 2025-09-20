<?php

namespace S3_Local_Index\Index\Exception;

use Exception;

abstract class IndexManagerException extends Exception
{
    public function __construct(
        protected string $id,
        string $message = "",
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getId(): string
    {
        return $this->id;
    }
}