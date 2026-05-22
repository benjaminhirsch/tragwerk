<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Credential;

use Laminas\Diactoros\Response\EmptyResponse;
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
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;

use function assert;
use function is_string;
use function iterator_to_array;

final readonly class TabHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private CredentialRepository $credentialRepository,
        private ServerRepository $serverRepository,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $credential = $this->resolveCredential($request);

        if (! $credential instanceof Credential) {
            return new EmptyResponse(200, ['HX-Redirect' => $this->urlHelper->generate('credential')]);
        }

        return match ($request->getAttribute('tab')) {
            'overview' => $this->renderOverview($request, $credential),
            'servers'  => $this->renderServers($request, $credential),
            default    => new EmptyResponse(404),
        };
    }

    private function renderOverview(ServerRequestInterface $request, Credential $credential): ResponseInterface
    {
        return $this->renderer->render($request, 'page::credential/tab/overview', ['credential' => $credential]);
    }

    private function renderServers(ServerRequestInterface $request, Credential $credential): ResponseInterface
    {
        $servers = iterator_to_array(
            $this->serverRepository->getAll(credentialId: $credential->id),
            false,
        );

        return $this->renderer->render($request, 'page::credential/tab/servers', [
            'credential' => $credential,
            'servers'    => $servers,
        ]);
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
