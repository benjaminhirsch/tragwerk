<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Metrics;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\AppMetricRepository;

use function assert;
use function is_string;

final readonly class LiveHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private AppMetricRepository $appMetrics,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $request->getAttribute('active_project');
        assert($project instanceof Project);

        $branch = $this->resolveBranch($request);

        if ($branch === null) {
            return new EmptyResponse(400);
        }

        return $this->renderer->render($request, 'page::metrics/_live', [
            'project' => $project,
            'branch'  => $branch,
            'metrics' => $this->appMetrics->getLatest($project->id, $branch),
        ]);
    }

    private function resolveBranch(ServerRequestInterface $request): string|null
    {
        $param = $request->getQueryParams()['branch'] ?? null;
        if (is_string($param) && $param !== '') {
            return $param;
        }

        $active = $request->getAttribute('active_environment');

        return is_string($active) && $active !== '' ? $active : null;
    }
}
