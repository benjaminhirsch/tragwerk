<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Registry;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\RegistryDeleted;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;

use function assert;
use function is_string;

final readonly class DeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private RegistryRepository $registryRepository,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $registry = $this->resolveRegistry($request);

        if ($registry instanceof Registry) {
            if ($this->registryRepository->isAssignedToProject($registry->id)) {
                $editUrl = $this->urlHelper->generate('registry.edit', ['id' => $registry->id->toString()]);

                return new RedirectResponse($editUrl . '?assigned=1');
            }

            $this->eventDispatcher->dispatch(new RegistryDeleted($registry->id));
        }

        return new RedirectResponse($this->urlHelper->generate('registry'));
    }

    private function resolveRegistry(ServerRequestInterface $request): Registry|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! RegistryIdentifier::isValid($routeId)) {
            return null;
        }

        $activeTeam = $request->getAttribute('active_team');
        if (! $activeTeam instanceof Team) {
            return null;
        }

        try {
            $registry = $this->registryRepository->getById(RegistryIdentifier::fromString($routeId));
            assert($registry instanceof Registry);

            if ($registry->teamId->toString() !== $activeTeam->id->toString()) {
                return null;
            }

            return $registry;
        } catch (Throwable) {
            return null;
        }
    }
}
