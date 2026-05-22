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
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;

use function assert;
use function is_string;

final readonly class ShowHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private CredentialRepository $credentialRepository,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $credential = $this->resolveCredential($request);

        if (! $credential instanceof Credential) {
            return new RedirectResponse($this->urlHelper->generate('credential'));
        }

        return $this->renderer->render($request, 'page::credential/show', ['credential' => $credential]);
    }

    private function resolveCredential(ServerRequestInterface $request): Credential|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! CredentialIdentifier::isValid($routeId)) {
            return null;
        }

        $activeTeam = $request->getAttribute('active_team');
        if (! $activeTeam instanceof Team) {
            return null;
        }

        try {
            $credential = $this->credentialRepository->getById(CredentialIdentifier::fromString($routeId));
            assert($credential instanceof Credential);

            if ($credential->teamId->toString() !== $activeTeam->id->toString()) {
                return null;
            }

            return $credential;
        } catch (Throwable) {
            return null;
        }
    }
}
