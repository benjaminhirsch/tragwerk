<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Server;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\SetupJob;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\SetupJobStatus;
use Tragwerk\Domain\Event\SetupJobScheduled;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\Repository\SetupJobRepository;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\SetupJobIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function assert;
use function is_string;

final readonly class SetupHandler implements RequestHandlerInterface
{
    public function __construct(
        private ServerRepository $serverRepository,
        private SetupJobRepository $setupJobRepository,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! ServerIdentifier::isValid($routeId)) {
            return new RedirectResponse($this->urlHelper->generate('server'));
        }

        $activeTeam = $request->getAttribute('active_team');
        if (! $activeTeam instanceof Team) {
            return new RedirectResponse($this->urlHelper->generate('server'));
        }

        try {
            $server = $this->serverRepository->getById(ServerIdentifier::fromString($routeId));
            assert($server instanceof Server);
        } catch (Throwable) {
            return new RedirectResponse($this->urlHelper->generate('server'));
        }

        if ($server->teamId->toString() !== $activeTeam->id->toString()) {
            return new RedirectResponse($this->urlHelper->generate('server'));
        }

        $existing = $this->setupJobRepository->getLatestForServer($server->id);
        if (
            $existing instanceof SetupJob
            && ($existing->status === SetupJobStatus::Pending || $existing->status === SetupJobStatus::Running)
        ) {
            return new RedirectResponse(
                $this->urlHelper->generate('server.show', ['id' => $server->id->toString()]) . '#setup',
            );
        }

        $now = TimestampImmutable::now();
        $job = new SetupJob(
            SetupJobIdentifier::create(),
            $server->id,
            SetupJobStatus::Pending,
            '',
            $now,
            $now,
        );

        $this->eventDispatcher->dispatch(new SetupJobScheduled($job));

        return new RedirectResponse(
            $this->urlHelper->generate('server.show', ['id' => $server->id->toString()]) . '#setup',
        );
    }
}
