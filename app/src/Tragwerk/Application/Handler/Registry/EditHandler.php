<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Registry;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Dto\Registry\Registry as RegistryDto;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\RegistryUpdated;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function is_string;

final readonly class EditHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private RegistryRepository $registryRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $registry = $this->resolveRegistry($request);

        if (! $registry instanceof Registry) {
            return new RedirectResponse($this->urlHelper->generate('registry'));
        }

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, RegistryDto::class);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof RegistryDto);

                $user = $request->getAttribute(UserInterface::class);
                assert($user instanceof UserInterface);

                $this->eventDispatcher->dispatch(new RegistryUpdated(
                    $registry,
                    $dto,
                    UserIdentifier::fromString($user->getIdentity()),
                ));

                return new RedirectResponse($this->urlHelper->generate('registry'));
            }
        }

        if ($validationBag === null) {
            $validationBag = new ValidationBag([
                'name'     => $registry->name,
                'url'      => $registry->url,
                'username' => $registry->username,
                'password' => $registry->password,
            ]);
        }

        return $this->renderer->render($request, 'page::registry/edit', [
            'registry'      => $registry,
            'validationBag' => $validationBag,
            'isAssigned'    => $this->registryRepository->isAssignedToProject($registry->id),
        ]);
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
