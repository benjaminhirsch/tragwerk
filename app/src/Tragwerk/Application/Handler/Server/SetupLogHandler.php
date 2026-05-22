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
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\SetupJob;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\SetupJobRepository;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\SetupJobIdentifier;

use function assert;
use function is_string;

final readonly class SetupLogHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ServerRepository $serverRepository,
        private SetupJobRepository $setupJobRepository,
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

        $jobId = $request->getAttribute('jobId');
        if (! is_string($jobId) || ! SetupJobIdentifier::isValid($jobId)) {
            return new EmptyResponse(200, ['HX-Redirect' => $this->urlHelper->generate('server')]);
        }

        try {
            $job = $this->setupJobRepository->getById(SetupJobIdentifier::fromString($jobId));
            assert($job instanceof SetupJob);
        } catch (Throwable) {
            return new EmptyResponse(200, ['HX-Redirect' => $this->urlHelper->generate('server')]);
        }

        if ($job->serverId->toString() !== $server->id->toString()) {
            return new EmptyResponse(200, ['HX-Redirect' => $this->urlHelper->generate('server')]);
        }

        return $this->renderer->render($request, 'page::server/_log_widget', [
            'server' => $server,
            'job'    => $job,
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
