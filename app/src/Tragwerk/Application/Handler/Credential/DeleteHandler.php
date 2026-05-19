<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Credential;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;

use function assert;
use function is_string;

final readonly class DeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private CredentialRepository $credentialRepository,
        private ServerRepository $serverRepository,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $credential = $this->resolveCredential($request);

        if ($credential instanceof Credential) {
            if ($this->serverRepository->isCredentialAssigned($credential->id)) {
                $editUrl = $this->urlHelper->generate('credential.edit', ['id' => $credential->id->toString()]);

                return new RedirectResponse($editUrl . '?assigned=1');
            }

            $this->credentialRepository->delete($credential->id);
        }

        return new RedirectResponse($this->urlHelper->generate('credential'));
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
