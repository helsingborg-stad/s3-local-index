<?php

namespace S3_Local_Index\Stream\Response;

interface ResponseTraitUrlStatInterface
{
    public function found(string $type = 'file', ?int $size = null): array;
    public function bypass(): null;
    public function notfound(): false;
}