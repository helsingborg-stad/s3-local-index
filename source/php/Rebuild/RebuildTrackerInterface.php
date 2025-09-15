<?php

namespace S3_Local_Index\Rebuild;


/**
 * Interface for tracking indexes that need to be rebuilt
 */
interface RebuildTrackerInterface
{
  /**
   * Add an index to the rebuild list
   *
   * @param string $blogId Blog ID
   * @param string $year Year
   * @param string $month Month
   * @return bool True on success, false on failure
   */
  public function addToRebuildList(string $blogId, string $year, string $month): bool;

  /**
   * Add index for a file path to the rebuild list
   *
   * @param string $path S3 file path
   * @return bool True if added to rebuild list, false if path doesn't match pattern
   */
  public function addPathToRebuildList(string $path): bool;

  /**
   * Get the current rebuild list
   *
   * @return array Array of rebuild keys in format "blogId-year-month"
   */
  public function getRebuildList(): array;

  /**
   * Clear the rebuild list
   *
   * @return bool True on success, false on failure
   */
  public function clearRebuildList(): bool;

  /**
   * Remove an item from the rebuild list
   *
   * @param string $blogId Blog ID
   * @param string $year Year
   * @param string $month Month
   * @return bool True on success, false on failure
   */
  public function removeFromRebuildList(string $blogId, string $year, string $month): bool;
}