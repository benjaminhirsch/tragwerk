<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Server;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\SetupJobStatus;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\SetupJobRepository;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

use function assert;
use function is_string;

final readonly class ShowHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ServerRepository $serverRepository,
        private CredentialRepository $credentialRepository,
        private SetupJobRepository $setupJobRepository,
        private ProjectRepository $projectRepository,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $server = $this->resolveServer($request);

        if (! $server instanceof Server) {
            return new RedirectResponse($this->urlHelper->generate('server'));
        }

        $activeTeam = $request->getAttribute('active_team');
        assert($activeTeam instanceof Team);

        $credential = null;
        if ($server->credentialId !== null) {
            try {
                $credential = $this->credentialRepository->getById($server->credentialId);
                assert($credential instanceof Credential);
            } catch (Throwable) {
            }
        }

        $latestJob = $this->setupJobRepository->getLatestForServer($server->id);
        $jobActive = $latestJob !== null
            && ($latestJob->status === SetupJobStatus::Pending || $latestJob->status === SetupJobStatus::Running);

        $workloads = [];
        foreach ($this->projectRepository->getAll($activeTeam->id) as $project) {
            assert($project instanceof Project);

            if ($project->serverId->toString() !== $server->id->toString()) {
                continue;
            }

            $workloads[] = $project;
        }

        $deleteBlocked = ($request->getQueryParams()['assigned'] ?? null) === '1';

        return $this->renderer->render($request, 'page::server/show', [
            'server'        => $server,
            'credential'    => $credential,
            'latestJob'     => $latestJob,
            'jobActive'     => $jobActive,
            'workloads'     => $workloads,
            'deleteBlocked' => $deleteBlocked,
        ]);
    }

    private function resolveServer(ServerRequestInterface $request): Server|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! ServerIdentifier::isValid($routeId)) {
            return null;
        }

        $activeTeam = $request->getAttribute('active_team');
        if (! $activeTeam instanceof Team) {
            return null;
        }

        try {
            $server = $this->serverRepository->getById(ServerIdentifier::fromString($routeId));
            assert($server instanceof Server);

            if ($server->teamId->toString() !== $activeTeam->id->toString()) {
                return null;
            }

            return $server;
        } catch (Throwable) {
            return null;
        }
    }
}
