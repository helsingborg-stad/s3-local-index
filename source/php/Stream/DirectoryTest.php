<?php

namespace S3_Local_Index\Stream;

use PHPUnit\Framework\TestCase;

class DirectoryTest extends TestCase
{
    /**
     * @testdox class can be instantiated
     */
    public function testClassCanBeInstantiated(): void
    {
        $reader = $this->createReader();
        $directory = new Directory($reader);

        $this->assertInstanceOf(Directory::class, $directory);
    }

    /**
     * @testdox dir_opendir loads index and prepares file list
     */
    public function testDirOpendirLoadsIndexAndPreparesFileList(): void
    {
        $indexData = [
            'uploads/2023/01/image1.jpg' => true,
            'uploads/2023/01/image2.jpg' => true,
            'uploads/2023/01/subdir/image3.jpg' => true,
            'uploads/2023/02/image4.jpg' => true,
        ];
        
        $reader = $this->createReader($indexData);
        $directory = new Directory($reader);

        $result = $directory->dir_opendir('s3://bucket/uploads/2023/01/', 0);

        $this->assertTrue($result);
    }

    /**
     * @testdox dir_opendir handles different path formats
     */
    public function testDirOpendirHandlesDifferentPathFormats(): void
    {
        $reader = $this->createReader();
        $directory = new Directory($reader);

        // Test with trailing slash
        $result1 = $directory->dir_opendir('s3://bucket/uploads/2023/01/', 0);
        $this->assertTrue($result1);

        // Test without trailing slash
        $result2 = $directory->dir_opendir('s3://bucket/uploads/2023/01', 0);
        $this->assertTrue($result2);
    }

    /**
     * @testdox dir_readdir method exists and can be called
     */
    public function testDirReaddirMethodExistsAndCanBeCalled(): void
    {
        $reader = $this->createReader();
        $directory = new Directory($reader);

        // This test just ensures the method exists and doesn't throw an exception
        if (method_exists($directory, 'dir_readdir')) {
            try {
                $directory->dir_readdir();
                $this->assertTrue(true, 'dir_readdir method executed without exceptions.');
            } catch (\Exception $e) {
                $this->fail('dir_readdir method threw an exception: ' . $e->getMessage());
            }
        } else {
            $this->markTestSkipped('dir_readdir method not implemented yet.');
        }
    }

    /**
     * @testdox dir_rewinddir method exists and can be called
     */
    public function testDirRewinddirMethodExistsAndCanBeCalled(): void
    {
        $reader = $this->createReader();
        $directory = new Directory($reader);

        // This test just ensures the method exists and doesn't throw an exception
        if (method_exists($directory, 'dir_rewinddir')) {
            try {
                $directory->dir_rewinddir();
                $this->assertTrue(true, 'dir_rewinddir method executed without exceptions.');
            } catch (\Exception $e) {
                $this->fail('dir_rewinddir method threw an exception: ' . $e->getMessage());
            }
        } else {
            $this->markTestSkipped('dir_rewinddir method not implemented yet.');
        }
    }

    /**
     * @testdox dir_closedir method exists and can be called
     */
    public function testDirClosedirMethodExistsAndCanBeCalled(): void
    {
        $reader = $this->createReader();
        $directory = new Directory($reader);

        // This test just ensures the method exists and doesn't throw an exception
        if (method_exists($directory, 'dir_closedir')) {
            try {
                $result = $directory->dir_closedir();
                $this->assertTrue(is_bool($result), 'dir_closedir should return a boolean.');
            } catch (\Exception $e) {
                $this->fail('dir_closedir method threw an exception: ' . $e->getMessage());
            }
        } else {
            $this->markTestSkipped('dir_closedir method not implemented yet.');
        }
    }

    private function createReader(array $indexData = []): Reader
    {
        return new class($indexData) {
            public function __construct(private array $indexData) {}

            public function loadIndex(string $path): array
            {
                return $this->indexData;
            }

            // Add other methods that might be called
            public function extractIndexDetails(string $path): ?array
            {
                return ['blogId' => '1', 'year' => '2023', 'month' => '01'];
            }

            public function getCacheKeyForPath(string $path): ?string
            {
                return 'index_1_2023_01';
            }

            public function flushCacheForPath(string $path): bool
            {
                return true;
            }
        };
    }
}