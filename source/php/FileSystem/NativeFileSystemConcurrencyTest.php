<?php

namespace S3_Local_Index\FileSystem;

use PHPUnit\Framework\TestCase;

class NativeFileSystemConcurrencyTest extends TestCase
{
    public function testConcurrentProcessesDoNotLoseIndexEntries(): void
    {
        if (!function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the multi-process test.');
        }

        $directory = sys_get_temp_dir() . '/s3-index-concurrency-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $file = $directory . '/index.json';
        $start = $directory . '/start';
        $workers = 8;
        $entriesPerWorker = 20;
        $children = [];

        try {
            for ($worker = 0; $worker < $workers; $worker++) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);
                if ($pid === 0) {
                    $fileSystem = new NativeFileSystem();
                    while (!file_exists($start)) {
                        usleep(1000);
                    }
                    try {
                        for ($entry = 0; $entry < $entriesPerWorker; $entry++) {
                            $key = "worker-{$worker}-{$entry}";
                            $fileSystem->mutateIndexFile($file, static function (array $index) use ($key): array {
                                // Make overlapping read/modify/write cycles likely.
                                usleep(1000);
                                $index[$key] = ['size' => 1];
                                return $index;
                            });
                        }
                    } catch (\Throwable $error) {
                        exit(1);
                    }
                    exit(0);
                }
                $children[] = $pid;
            }

            touch($start);
            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status));
                $this->assertSame(0, pcntl_wexitstatus($status));
            }

            $index = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $this->assertCount($workers * $entriesPerWorker, $index);
        } finally {
            foreach (glob($directory . '/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }

    public function testCorruptIndexIsNotOverwritten(): void
    {
        $directory = sys_get_temp_dir() . '/s3-index-corrupt-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $file = $directory . '/index.json';
        file_put_contents($file, '{broken');

        try {
            $this->expectException(\RuntimeException::class);
            (new NativeFileSystem())->mutateIndexFile($file, static fn(array $index): array => $index);
        } finally {
            $this->assertSame('{broken', file_get_contents($file));
            foreach (glob($directory . '/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }

    public function testUnwritableIndexPathFailsInsteadOfReportingSuccess(): void
    {
        $directory = sys_get_temp_dir() . '/s3-index-unwritable-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $notDirectory = $directory . '/file';
        file_put_contents($notDirectory, 'content');

        try {
            $this->expectException(\RuntimeException::class);
            (new NativeFileSystem())->mutateIndexFile(
                $notDirectory . '/index.json',
                static fn(array $index): array => $index,
            );
        } finally {
            unlink($notDirectory);
            rmdir($directory);
        }
    }
}
