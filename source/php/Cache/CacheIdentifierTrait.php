<?php

namespace S3_Local_Index\Cache;

trait CacheIdentifierTrait
{
    /**
     * Create a cache identifier string.
     *
     * @param array $details {
     *     @type int|string $blogId Blog/site ID.
     *     @type int|string $year   Year.
     *     @type int|string $month  Month.
     * }
     *
     * @return string Cache identifier.
     */
    public function createCacheIdentifier(array $details): ?string
    {
        if(isset($details['blogId']) && isset($details['year']) && isset($details['month'])) {
          return "index_{$details['blogId']}_{$details['year']}_{$details['month']}"; 
        } 
        return null;
    }
}