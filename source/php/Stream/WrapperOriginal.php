<?php 

namespace S3_Local_Index\Stream;
use S3_Local_Index\Stream\WrapperInterface;
use S3_Uploads\Stream_Wrapper as StreamWrapperOriginal;

class WrapperOriginal extends StreamWrapperOriginal implements WrapperInterface
{
}