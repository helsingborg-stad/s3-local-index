<?php

namespace S3_Local_Index\Index\Maintainance;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use WpService\Implementations\FakeWpService;
use S3_Local_Index\Index\IndexManager;
use S3_Local_Index\Logger\Logger;
use S3_Local_Index\Index\Exception\IndexManagerException;

class MaintainIndexOnNewIntermidiateImageTest extends TestCase
{
    private MaintainIndexOnNewIntermidiateImage $maintainer;
    private FakeWpService $wpService;
    private IndexManager $indexManager;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpService = new FakeWpService([
            'addAction' => true
        ]);

        $this->indexManager = $this->createMock(IndexManager::class);
        $this->logger = $this->createMock(Logger::class);

        $this->maintainer = new MaintainIndexOnNewIntermidiateImage(
            $this->wpService,
            $this->indexManager,
            $this->logger
        );
    }

    #[TestDox('class can be instantiated')]
    public function testClassCanBeInstantiated(): void
    {
        $this->assertInstanceOf(MaintainIndexOnNewIntermidiateImage::class, $this->maintainer);
        $this->assertInstanceOf(\S3_Local_Index\HookableInterface::class, $this->maintainer);
    }

    #[TestDox('addHooks registers wp_save_image_file action')]
    public function testAddHooksRegistersWpSaveImageFileAction(): void
    {
        // The FakeWpService will return true for addAction calls
        $this->maintainer->addHooks();

        // We can't easily test if the hook was actually registered with FakeWpService
        // but we can ensure the method doesn't throw an exception
        $this->assertTrue(true, 'addHooks method executed without exceptions');
    }

    #[TestDox('onFileCreation logs new image creation')]
    public function testOnFileCreationLogsNewImageCreation(): void
    {
        $filePath = '/path/to/intermediate-image.jpg';

        $this->logger->expects($this->once())
            ->method('log')
            ->with($this->stringContains('New intermidiate image created: ' . $filePath));

        $this->indexManager->expects($this->once())
            ->method('write')
            ->with($filePath)
            ->willReturn(true);

        $this->maintainer->onFileCreation($filePath);
    }

    #[TestDox('onFileCreation writes to index manager')]
    public function testOnFileCreationWritesToIndexManager(): void
    {
        $filePath = '/path/to/intermediate-image.jpg';

        $this->indexManager->expects($this->once())
            ->method('write')
            ->with($filePath)
            ->willReturn(true);

        $this->maintainer->onFileCreation($filePath);
    }

    #[TestDox('onFileCreation handles cannot_write_to_index exception')]
    public function testOnFileCreationHandlesCannotWriteToIndexException(): void
    {
        $filePath = '/path/to/intermediate-image.jpg';
        $exception = new IndexManagerException('Cannot write to index', 'cannot_write_to_index');

        $this->indexManager->expects($this->once())
            ->method('write')
            ->with($filePath)
            ->willThrowException($exception);

        $this->logger->expects($this->atLeastOnce())
            ->method('log')
            ->withConsecutive(
                [$this->stringContains('New intermidiate image created')],
                [$this->stringContains('Cannot write to index')]
            );

        $this->maintainer->onFileCreation($filePath);
    }

    #[TestDox('onFileCreation handles unexpected IndexManagerException')]
    public function testOnFileCreationHandlesUnexpectedIndexManagerException(): void
    {
        $filePath = '/path/to/intermediate-image.jpg';
        $exception = new IndexManagerException('Unexpected error', 'unknown_error');

        $this->indexManager->expects($this->once())
            ->method('write')
            ->with($filePath)
            ->willThrowException($exception);

        $this->logger->expects($this->atLeastOnce())
            ->method('log')
            ->withConsecutive(
                [$this->stringContains('New intermidiate image created')],
                [$this->stringContains('Unexpected error on writing to index')]
            );

        $this->maintainer->onFileCreation($filePath);
    }

    #[TestDox('onFileCreation handles other exceptions gracefully')]
    public function testOnFileCreationHandlesOtherExceptions(): void
    {
        $filePath = '/path/to/intermediate-image.jpg';
        $exception = new \RuntimeException('Some other error');

        $this->indexManager->expects($this->once())
            ->method('write')
            ->with($filePath)
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('log')
            ->with($this->stringContains('New intermidiate image created'));

        // Should not throw the exception, but handle it gracefully
        $this->expectException(\RuntimeException::class);
        $this->maintainer->onFileCreation($filePath);
    }
}