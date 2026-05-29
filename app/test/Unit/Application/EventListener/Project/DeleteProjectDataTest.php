<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\EventListener\Project;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\EventListener\Project\DeleteProjectData;
use Tragwerk\Domain\Event\ProjectDeleted;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class DeleteProjectDataTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/tw-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (! is_dir($this->tempDir)) {
            return;
        }

        $this->removeDirectory($this->tempDir);
    }

    #[Test]
    public function doesNothingWhenProjectDirectoryDoesNotExist(): void
    {
        $listener  = new DeleteProjectData($this->tempDir);
        $projectId = ProjectIdentifier::create();

        $listener(new ProjectDeleted($projectId));

        self::assertDirectoryDoesNotExist($this->tempDir . '/' . $projectId->toString());
    }

    #[Test]
    public function removesProjectDirectoryOnDeletion(): void
    {
        $listener   = new DeleteProjectData($this->tempDir);
        $projectId  = ProjectIdentifier::create();
        $projectDir = $this->tempDir . '/' . $projectId->toString();

        mkdir($projectDir, 0755, true);

        $listener(new ProjectDeleted($projectId));

        self::assertDirectoryDoesNotExist($projectDir);
    }

    #[Test]
    public function removesDirectoryWithNestedFilesAndSubdirectories(): void
    {
        $listener   = new DeleteProjectData($this->tempDir);
        $projectId  = ProjectIdentifier::create();
        $projectDir = $this->tempDir . '/' . $projectId->toString();
        $branchDir  = $projectDir . '/main';

        mkdir($branchDir, 0755, true);
        file_put_contents($branchDir . '/docker-compose.yml', 'version: "3"');
        file_put_contents($branchDir . '/build.zip', 'PK');

        $listener(new ProjectDeleted($projectId));

        self::assertDirectoryDoesNotExist($projectDir);
    }

    #[Test]
    public function doesNotRemoveSiblingProjectDirectories(): void
    {
        $listener  = new DeleteProjectData($this->tempDir);
        $deleteId  = ProjectIdentifier::create();
        $keepId    = ProjectIdentifier::create();
        $deleteDir = $this->tempDir . '/' . $deleteId->toString();
        $keepDir   = $this->tempDir . '/' . $keepId->toString();

        mkdir($deleteDir, 0755, true);
        mkdir($keepDir, 0755, true);
        file_put_contents($keepDir . '/build.zip', 'PK');

        $listener(new ProjectDeleted($deleteId));

        self::assertDirectoryDoesNotExist($deleteDir);
        self::assertDirectoryExists($keepDir);
        self::assertFileExists($keepDir . '/build.zip');
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;
            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }
}
