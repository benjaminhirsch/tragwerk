<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Server;

use Laminas\Diactoros\Response\EmptyResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\SetupJobStatus;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\SetupJobRepository;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

use function assert;
use function is_string;

final readonly class TabHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ServerRepository $serverRepository,
        private SetupJobRepository $setupJobRepository,
        private CredentialRepository $credentialRepository,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $server = $this->resolveServer($request);

        if (! $server instanceof Server) {
            return new EmptyResponse(200, ['HX-Redirect' => $this->urlHelper->generate('server')]);
        }

        return match ($request->getAttribute('tab')) {
            'overview' => $this->renderOverview($request, $server),
            'setup'    => $this->renderSetup($request, $server),
            default    => new EmptyResponse(404),
        };
    }

    private function renderOverview(ServerRequestInterface $request, Server $server): ResponseInterface
    {
        $credential = null;

        if ($server->credentialId !== null) {
            try {
                $credential = $this->credentialRepository->getById($server->credentialId);
                assert($credential instanceof Credential);
            } catch (Throwable) {
            }
        }

        return $this->renderer->render($request, 'page::server/tab/overview', [
            'server'     => $server,
            'credential' => $credential,
        ]);
    }

    private function renderSetup(ServerRequestInterface $request, Server $server): ResponseInterface
    {
        $latestJob = $this->setupJobRepository->getLatestForServer($server->id);
        $jobActive = $latestJob !== null
            && ($latestJob->status === SetupJobStatus::Pending || $latestJob->status === SetupJobStatus::Running);

        return $this->renderer->render($request, 'page::server/tab/setup', [
            'server'    => $server,
            'latestJob' => $latestJob,
            'jobActive' => $jobActive,
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
