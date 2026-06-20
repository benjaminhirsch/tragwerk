<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Metrics;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\ProjectConfigLoader;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;
use function in_array;
use function is_string;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private BareRepository $bareRepository,
        private ProjectConfigLoader $configLoader,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $request->getAttribute('active_project');
        assert($project instanceof Project);

        $environments = $this->bareRepository->getBranches($project->id->toString());

        $requested         = $request->getQueryParams()['branch'] ?? null;
        $activeEnvironment = $request->getAttribute('active_environment');

        $branch = match (true) {
            is_string($requested) && $requested !== ''                 => $requested,
            is_string($activeEnvironment) && $activeEnvironment !== '' => $activeEnvironment,
            default                                                    => null,
        };

        if (($branch === null || ! in_array($branch, $environments, true)) && $environments !== []) {
            $branch = $environments[0];
        }

        if (! in_array($branch, $environments, true)) {
            $branch = null;
        }

        return $this->renderer->render($request, 'page::metrics/index', [
            'project'      => $project,
            'environments' => $environments,
            'branch'       => $branch,
            'workerMode'   => $branch !== null ? $this->isWorkerMode($project, $branch) : null,
        ]);
    }

    /**
     * Worker vs classic mode is an XML config property per application. The environment runs in
     * worker mode if any of its applications declares <worker>. Returns null when no config is
     * available for the branch (mode unknown).
     */
    private function isWorkerMode(Project $project, string $branch): bool|null
    {
        $config = $this->configLoader->load($project->id, $branch);

        if ($config === null) {
            return null;
        }

        foreach ($config->applications as $application) {
            if ($application->workerMode !== null) {
                return true;
            }
        }

        return false;
    }
}
