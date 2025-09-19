<?php 
namespace S3_Local_Index\Stream;

enum ReaderEnumUrlStat: string
{
    case NoIndex   = 'no_index';
    case NotFound  = 'not_found';
    case File      = 'file';
    case Directory = 'directory';
}