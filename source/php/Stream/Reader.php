<?php

namespace S3_Local_Index\Stream;

class Reader {

    private static array $index = [];

    private string $key = '';
    private int $position = 0;

    public static function loadIndex(string $path): array {
        $path = ltrim($path, '/');
        if (!preg_match('#uploads(?:/networks/\d+/sites/(\d+))?/(\d{4})/(\d{2})/#', $path, $m)) {
            return [];
        }

        $blog_id = $m[1] ?? '1';
        $year    = $m[2];
        $month   = $m[3];

        $file = sys_get_temp_dir() . "/s3-index-temp/s3-index-{$blog_id}-{$year}-{$month}.json";
        if (!file_exists($file)) {
            return [];
        }

        $data = file_get_contents($file);
        return json_decode($data, true) ?: [];
    }

    public function stream_open(string $path, string $mode, int $options, &$opened_path): bool {
        self::$index = self::loadIndex($path);
        $normalized = $this->normalize($path);
        if (!isset(self::$index[$normalized])) {
            return false;
        }

        $this->key = $normalized;
        $this->position = 0;
        return true;
    }

    public function stream_read(int $count): string {
        $data = file_get_contents('s3://' . $this->key);
        $chunk = substr($data, $this->position, $count);
        $this->position += strlen($chunk);
        return $chunk;
    }

    public function stream_eof(): bool {
        $data = file_get_contents('s3://' . $this->key);
        return $this->position >= strlen($data);
    }

    public function url_stat(string $path, int $flags) {
        self::$index = self::loadIndex($path);
        $normalized = $this->normalize($path);
        return isset(self::$index[$normalized]) ? ['size' => 1, 'mtime' => time()] : false;
    }

    private function normalize(string $path): string {
        return ltrim(str_replace('s3://', '', $path), '/');
    }
}