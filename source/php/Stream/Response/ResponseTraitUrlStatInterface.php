<?php

/**
 * Response trait URL stat interface.
 */

namespace S3_Local_Index\Stream\Response;

/**
 * Interface for URL stat response methods.
 */
interface ResponseTraitUrlStatInterface
{
    /**
     * Returns a stat array indicating a found resource.
     *
     * @param string   $type The type of resource ('file' or 'dir').
     * @param int|null $size The size of the resource in bytes.
     * @return array The stat array.
     */
    public function found(string $type = 'file', ?int $size = null): array;

    /**
     * Returns null to bypass the stream wrapper.
     *
     * @return null
     */
    public function bypass(): null;

    /**
     * Returns false indicating a resource was not found.
     *
     * @return false
     */
    public function notfound(): false;
}