<?php

namespace S3_Local_Index\Stream\Response;

use S3_Local_Index\Stream\Response\ResponseTraitUrlStatInterface;

/**
 * Trait ResponseTrait
 *
 * Provides common response handling methods for stream wrapper operations.
 */
trait ResponseTrait
{
    /**
     * Get simulated, named responses for a url_stat call.
     *
     * @return ResponseTraitUrlStatInterface
     */
    public function url_stat_response(): ResponseTraitUrlStatInterface
    {
        return new class implements ResponseTraitUrlStatInterface {

            /**
             * Return a simulated stat array for a found file or directory.
             *
             * @param  string $type Either 'file' or 'dir'.
             * @return array
             */
            public function found(string $type = 'file', ?string $path = null): array
            {
                if($type !== 'file' && $type !== 'dir') {
                    throw new \InvalidArgumentException('Type must be either "file" or "dir".');
                }

                $isDir = $type === 'dir';
                $time  = time();

                // Current user/group IDs
                $uid = function_exists('posix_getuid') ? posix_getuid() : 0;
                $gid = function_exists('posix_getgid') ? posix_getgid() : 0;

                // Determine default WordPress permissions
                $fileMode = defined('FS_CHMOD_FILE')
                ? FS_CHMOD_FILE
                : (fileperms(ABSPATH) & 0777 | 0644);

                $dirMode = defined('FS_CHMOD_DIR')
                ? FS_CHMOD_DIR
                : (fileperms(ABSPATH) & 0777 | 0755);

                // Compute mode and size
                $mode = $isDir
                ? (0040000 | $dirMode)   // Directory
                : (0100000 | $fileMode); // Regular file

                $size = $isDir ? 0 : (
                    $this->getFilesizeFromDatabase($path) ?? 1024
                );

                $blocks = ceil($size / 512) ?: 0;

                // Common stat fields
                $stat = [
                'dev'     => 0,        // Device (not used)
                'ino'     => 0,        // File ID (not used)
                'mode'    => $mode,    // Mode (type + permissions)
                'nlink'   => 1,        // Number of links
                'uid'     => $uid,     // PHP process user ID
                'gid'     => $gid,     // PHP process group ID
                'rdev'    => 0,        // Device type (not used)
                'size'    => $size,    // File or directory size
                'atime'   => $time,    // Last access time
                'mtime'   => $time,    // Last modification time
                'ctime'   => $time,    // Last status change
                'blksize' => 4096,     // Typical filesystem block size
                'blocks'  => $blocks   // Approx block count
                ];

                return $stat;
            }

            public function bypass(): null
            {
                return null;
            }

            public function notfound(): false
            {
                return false;
            }

            /**
             * Attempt to get the filesize from the database by path.
             *
             * @param string $path The file path on disk.
             * @return int|null The filesize in bytes, or null if not found.
             */
            private function getFilesizeFromDatabase(?string $path): ?int
            {
                if($path === null) {
                    return null;
                }

                global $wpdb;

                // Adjust table/column names as needed for your schema
                $table = $wpdb->prefix . 'your_file_index_table';
                $sql = $wpdb->prepare(
                    "SELECT filesize FROM $table WHERE path = %s LIMIT 1",
                    $path
                );
                $result = $wpdb->get_var($sql);

                return ($result !== null) ? (int)$result : null;
            }
        };
    }
}
