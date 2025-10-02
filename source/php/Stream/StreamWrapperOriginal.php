<?php 

namespace S3_Local_Index\Stream;
use S3_Uploads\Stream_Wrapper as S3PluginStreamWrapperOriginal;

/**
 * Original S3 stream wrapper class to delegate calls to.
 * 
 * This class extends the original S3 stream wrapper and implements
 * the WrapperInterface to ensure compatibility with the custom
 * stream wrapper implementation.
 * 
 * This class cannot be validated, due to the fact that it only acts 
 * as a proxy, holding the original S3 stream wrapper.
 */
class StreamWrapperOriginal extends S3PluginStreamWrapperOriginal implements StreamWrapperInterface
{
}