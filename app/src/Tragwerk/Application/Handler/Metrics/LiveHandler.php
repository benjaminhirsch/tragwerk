<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Metrics;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Infrastructure\Metrics\EnvironmentMetricsCollector;

use function assert;
use function is_string;

final readonly class LiveHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ServerRepository $serverRepository,
        private CredentialRepository $credentialRepository,
        private EnvironmentMetricsCollector $collector,
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

        $metrics = null;
        $error   = null;

        try {
            $server = $this->serverRepository->getById($project->serverId);
            assert($server instanceof Server);

            if ($server->credentialId === null) {
                throw new RuntimeException('No credential assigned to server.');
            }

            $credential = $this->credentialRepository->getById($server->credentialId);
            assert($credential instanceof Credential);

            $metrics = $this->collector->collect($project, $branch, $server, $credential);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return $this->renderer->render($request, 'page::metrics/_live', [
            'project' => $project,
            'branch'  => $branch,
            'metrics' => $metrics,
            'error'   => $error,
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
