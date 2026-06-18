<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Configuration;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\ProjectConfigLoader;
use Tragwerk\Application\Service\VolumeSizeReader;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Model\ApplicationConfig;

use function assert;
use function is_string;
use function strlen;
use function substr;

final readonly class MountSizesHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectConfigLoader $configLoader,
        private VolumeSizeReader $volumeSizeReader,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $request->getAttribute('active_project');
        assert($project instanceof Project);

        $branch = $request->getAttribute('active_environment');
        assert(is_string($branch));

        $projectConfig = $this->configLoader->load($project->id, $branch);
        $app           = $projectConfig?->applications[0] ?? null;
        $services      = $projectConfig?->services ?? [];

        $hasMounts = $app instanceof ApplicationConfig && $app->mounts !== [];

        $sizes = [];
        $error = null;

        if ($hasMounts || $services !== []) {
            try {
                $prefix = $this->volumeSizeReader->composeProjectName($project, $branch) . '_';

                foreach ($this->volumeSizeReader->read($project, $branch) as $name => $size) {
                    $sizes[substr($name, strlen($prefix))] = $size;
                }
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }

        return $this->renderer->render($request, 'page::configuration/_mount_sizes', [
            'app'      => $app,
            'services' => $services,
            'sizes'    => $sizes,
            'error'    => $error,
        ]);
    }
}
