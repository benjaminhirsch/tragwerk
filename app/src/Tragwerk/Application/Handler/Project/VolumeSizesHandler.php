<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\VolumeSizeReader;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function assert;
use function is_array;
use function is_string;
use function json_decode;

final readonly class VolumeSizesHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private VolumeSizeReader $volumeSizeReader,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        if (! $project instanceof Project) {
            return new EmptyResponse(404);
        }

        $params = $request->getQueryParams();
        $branch = is_string($params['branch'] ?? null) ? $params['branch'] : null;

        if ($branch === null || $branch === '') {
            return new EmptyResponse(400);
        }

        $volumes = [];
        $error   = null;

        try {
            $volumes = $this->volumeSizeReader->read($project, $branch);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $descRaw      = is_string($params['desc'] ?? null) ? $params['desc'] : '{}';
        $decoded      = json_decode($descRaw, true);
        $descriptions = is_array($decoded) ? $decoded : [];

        return $this->renderer->render($request, 'page::project/_volume_sizes', [
            'project'      => $project,
            'branch'       => $branch,
            'volumes'      => $volumes,
            'descriptions' => $descriptions,
            'error'        => $error,
        ]);
    }

    private function resolveProject(ServerRequestInterface $request): Project|null
    {
        $routeId = $request->getAttribute('id');

        if (! is_string($routeId) || ! ProjectIdentifier::isValid($routeId)) {
            return null;
        }

        $activeTeam = $request->getAttribute('active_team');

        if (! $activeTeam instanceof Team) {
            return null;
        }

        try {
            $project = $this->projectRepository->getById(ProjectIdentifier::fromString($routeId));
            assert($project instanceof Project);

            if ($project->teamId->toString() !== $activeTeam->id->toString()) {
                return null;
            }

            return $project;
        } catch (Throwable) {
            return null;
        }
    }
}
