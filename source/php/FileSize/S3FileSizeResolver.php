<?php

namespace S3_Local_Index\FileSize;

use S3_Uploads\Plugin as S3Plugin;
use S3_Local_Index\Logger\LoggerInterface;
use Aws\Exception\AwsException;

/**
 * Resolves file sizes from S3 using headObject.
 */
class S3FileSizeResolver implements FileSizeResolverInterface
{
    public function __construct(
        private S3Plugin $s3Plugin,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get the file size from S3 for a given path.
     *
     * @param string $path The file path (can be full s3:// URL or bucket-relative path)
     * @return int|null The file size in bytes, or null if unable to determine
     */
    public function getFileSize(string $path): ?int
    {
        $s3     = $this->s3Plugin->s3();
        $bucket = $this->s3Plugin->get_s3_bucket();
        $key    = $this->extractS3Key($path, $bucket);

        if ($key === null) {
            $this->logger->log("[S3FileSizeResolver] Could not extract S3 key from path: {$path}");
            return null;
        }

        try {
            $result = $s3->headObject([
                'Bucket' => $bucket,
                'Key'    => $key,
            ]);

            $size = $result['ContentLength'] ?? null;

            if ($size !== null) {
                return (int) $size;
            }

        } catch (AwsException $e) {
            $this->logger->log("[S3FileSizeResolver] AWS error getting size for {$key}: {$e->getMessage()}");
        } catch (\Exception $e) {
            $this->logger->log("[S3FileSizeResolver] Error getting size for {$key}: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Extract the S3 key from various path formats.
     *
     * @param string $path   The file path
     * @param string $bucket The S3 bucket name
     * @return string|null The S3 key, or null if extraction failed
     */
    private function extractS3Key(string $path, string $bucket): ?string
    {
        // Handle s3://bucket/key format
        if (str_starts_with($path, 's3://')) {
            $withoutScheme = substr($path, 5);
            // Remove bucket name if present
            if (str_starts_with($withoutScheme, $bucket . '/')) {
                return substr($withoutScheme, strlen($bucket) + 1);
            }
            // Path might just be s3://key
            return $withoutScheme;
        }

        // Handle bucket/key format
        if (str_starts_with($path, $bucket . '/')) {
            return substr($path, strlen($bucket) + 1);
        }

        // Handle absolute paths that might contain uploads directory
        // Extract path after uploads directory
        if (preg_match('#/uploads/(.+)$#', $path, $matches)) {
            return 'uploads/' . $matches[1];
        }
        
        return $path;
    }
}
