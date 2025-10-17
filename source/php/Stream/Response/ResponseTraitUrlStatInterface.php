<?php

namespace S3_Local_Index\Stream\Response;

interface ResponseTraitUrlStatInterface
{
    public function found(): array;
    public function bypass(): null;
    public function notfound(): false;
}