<?php

namespace S3_Local_Index\Stream;

class Directory {

    private array $dir_keys = [];
    private int $dir_position = 0;

    public static array $index = [];

    public function dir_opendir(string $path, int $options): bool {
        self::$index = Reader::loadIndex($path);
        $this->dir_keys = [];

        $prefix = rtrim(str_replace('s3://', '', $path), '/') . '/';
        foreach (self::$index as $key => $_) {
            if (str_starts_with($key, $prefix)) {
                $this->dir_keys[] = substr($key, strlen($prefix));
            }
        }

        $this->dir_position = 0;
        return true;
    }

    public function dir_readdir(): false|string {
        if ($this->dir_position < count($this->dir_keys)) {
            return $this->dir_keys[$this->dir_position++];
        }
        return false;
    }

    public function dir_closedir(): void {
        $this->dir_keys = [];
    }
}