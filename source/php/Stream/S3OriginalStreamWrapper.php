<?php

namespace S3_Local_Index\Stream;

use S3_Uploads\Stream_Wrapper as S3UploadsStreamWrapper;

/**
 * Wrapper for the original S3 Uploads stream wrapper.
 * 
 * This class extends the original S3 stream wrapper and implements
 * the WrapperInterface to ensure compatibility with the custom
 * stream wrapper implementation. It provides access to the original
 * S3 functionality when local index resolution is not sufficient.
 * 
 * This class acts as a bridge between our custom stream system
 * and the existing S3 Uploads plugin functionality.
 */
class S3OriginalStreamWrapper extends S3UploadsStreamWrapper implements WrapperInterface
{
    // No additional implementation needed - inherits all functionality
    // from S3UploadsStreamWrapper and implements WrapperInterface
}