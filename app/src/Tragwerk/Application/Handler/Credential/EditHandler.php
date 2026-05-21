<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Credential;

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
use Tragwerk\Application\Dto\Credential\Credential as CredentialDto;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Event\CredentialUpdated;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
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
        private CredentialRepository $credentialRepository,
        private ServerRepository $serverRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $credential = $this->resolveCredential($request);

        if (! $credential instanceof Credential) {
            return new RedirectResponse($this->urlHelper->generate('credential'));
        }

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, CredentialDto::class);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof CredentialDto);

                $user = $request->getAttribute(UserInterface::class);
                assert($user instanceof UserInterface);

                $this->eventDispatcher->dispatch(new CredentialUpdated(
                    $credential->id,
                    $dto,
                    UserIdentifier::fromString($user->getIdentity()),
                ));

                return new RedirectResponse($this->urlHelper->generate('credential'));
            }
        }

        if ($validationBag === null) {
            $validationBag = new ValidationBag([
                'name'       => $credential->name,
                'username'   => $credential->username,
                'privateKey' => $credential->privateKey ?? '',
            ]);
        }

        $queryParams = $request->getQueryParams();

        return $this->renderer->render($request, 'page::credential/edit', [
            'credential'    => $credential,
            'validationBag' => $validationBag,
            'isAssigned'    => $this->serverRepository->isCredentialAssigned($credential->id),
            'deleteBlocked' => isset($queryParams['assigned']),
        ]);
    }

    private function resolveCredential(ServerRequestInterface $request): Credential|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! CredentialIdentifier::isValid($routeId)) {
            return null;
        }

        $activeProject = $request->getAttribute('active_project');
        if (! $activeProject instanceof Project) {
            return null;
        }

        try {
            $credential = $this->credentialRepository->getById(CredentialIdentifier::fromString($routeId));
            assert($credential instanceof Credential);

            if ($credential->projectId->toString() !== $activeProject->id->toString()) {
                return null;
            }

            return $credential;
        } catch (Throwable) {
            return null;
        }
    }
}
