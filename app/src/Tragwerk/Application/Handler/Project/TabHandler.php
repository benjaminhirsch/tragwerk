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
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\GitForge;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ProjectWebhookRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function assert;
use function in_array;
use function is_string;

final readonly class TabHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
        private TeamRepository $teamRepository,
        private RegistryRepository $registryRepository,
        private ProjectWebhookRepository $webhookRepository,
        private string $gitSshHost,
        private string $gitSshRepoBase,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        if (! $project instanceof Project) {
            return new EmptyResponse(404);
        }

        $tab = $request->getAttribute('tab');

        return match ($tab) {
            'overview'     => $this->renderOverview($request, $project),
            'environments' => $this->renderEnvironments($request, $project),
            'webhooks'     => $this->renderWebhooks($request, $project),
            default        => new EmptyResponse(404),
        };
    }

    private function renderOverview(ServerRequestInterface $request, Project $project): ResponseInterface
    {
        $server   = $this->serverRepository->getById($project->serverId);
        $team     = $this->teamRepository->getById($project->teamId);
        $cloneUrl = 'git@' . $this->gitSshHost . ':' . $this->gitSshRepoBase . '/' . $project->id->toString();

        assert($server instanceof Server);
        assert($team instanceof Team);

        $registry = null;
        try {
            $r = $this->registryRepository->getById($project->registryId);
            if ($r instanceof Registry) {
                $registry = $r;
            }
        } catch (Throwable) {
        }

        return $this->renderer->render($request, 'page::project/tab/overview', [
            'project'  => $project,
            'server'   => $server,
            'team'     => $team,
            'registry' => $registry,
            'cloneUrl' => $cloneUrl,
        ]);
    }

    private function renderEnvironments(ServerRequestInterface $request, Project $project): ResponseInterface
    {
        return $this->renderer->render($request, 'page::project/tab/environments', ['project' => $project]);
    }

    private function renderWebhooks(ServerRequestInterface $request, Project $project): ResponseInterface
    {
        $integrations = $this->webhookRepository->findByProject($project->id);
        $usedForges   = [];
        foreach ($integrations as $i) {
            $usedForges[] = $i->forge;
        }

        $availableForges = [];
        foreach (GitForge::cases() as $case) {
            if (in_array($case, $usedForges, true)) {
                continue;
            }

            $availableForges[] = $case;
        }

        $uri     = $request->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();

        return $this->renderer->render($request, 'page::project/tab/webhooks', [
            'project'         => $project,
            'integrations'    => $integrations,
            'availableForges' => $availableForges,
            'baseUrl'         => $baseUrl,
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
