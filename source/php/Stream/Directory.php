<?php

namespace S3_Local_Index\Stream;

class Directory {

    private array $dirKeys = [];
    private int $dirPosition = 0;
    private array $index = [];

    /**
     * Constructor with dependency injection
     *
     * @param Reader $reader
     */
    public function __construct(
        private Reader $reader
    ) {
    }

    public function dir_opendir(string $path, int $options): bool {
        $this->index = $this->reader->loadIndex($path);
        $this->dirKeys = [];

        $prefix = rtrim(str_replace('s3://', '', $path), '/') . '/';
        foreach ($this->index as $key => $_) {
            if (str_starts_with($key, $prefix)) {
                $this->dirKeys[] = substr($key, strlen($prefix));
            }
        }

        $this->dirPosition = 0;
        return true;
    }

    public function dir_readdir(): false|string {
        if ($this->dirPosition < count($this->dirKeys)) {
            return $this->dirKeys[$this->dirPosition++];
        }
        return false;
    }

    public function dir_closedir(): void {
        $this->dirKeys = [];
    }
}