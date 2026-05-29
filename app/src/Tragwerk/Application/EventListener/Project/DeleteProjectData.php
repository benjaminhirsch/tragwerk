<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Tragwerk\Domain\Event;

use function fclose;
use function is_dir;
use function proc_close;
use function proc_open;
use function rtrim;
use function stream_get_contents;

final readonly class DeleteProjectData
{
    public function __construct(private string $projectDataPath)
    {
    }

    public function __invoke(Event\ProjectDeleted $event): void
    {
        $path = rtrim($this->projectDataPath, '/') . '/' . $event->projectId->toString();

        if (! is_dir($path)) {
            return;
        }

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process     = proc_open(['rm', '-rf', $path], $descriptors, $pipes);

        if ($process === false) {
            return;
        }

        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_close($process);
    }
}
