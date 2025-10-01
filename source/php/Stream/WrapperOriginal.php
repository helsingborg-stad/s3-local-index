<?php 

namespace S3_Local_Index\Stream;
use S3_Local_Index\Stream\WrapperInterface;
use S3_Uploads\Stream_Wrapper as StreamWrapperOriginal;

/**
 * Original S3 stream wrapper class to delegate calls to.
 * 
 * This class extends the original S3 stream wrapper and implements
 * the WrapperInterface to ensure compatibility with the custom
 * stream wrapper implementation.
 */
class WrapperOriginal extends StreamWrapperOriginal implements WrapperInterface
{
}