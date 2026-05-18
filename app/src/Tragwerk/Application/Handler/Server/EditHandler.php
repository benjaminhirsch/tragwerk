<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Server;

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
use Tragwerk\Application\Dto\Server\Server as ServerDto;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Event\ServerUpdated;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function assert;
use function is_string;

final readonly class EditHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private ServerRepository $serverRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $server = $this->resolveServer($request);

        if (! $server instanceof Server) {
            return new RedirectResponse($this->urlHelper->generate('server'));
        }

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, ServerDto::class);

            if (! $validationBag->hasErrors()) {
                $update = $validationBag->getDto();
                assert($update instanceof ServerDto);

                if ($this->serverRepository->existsByHost($update->host, $server->id)) {
                    $validationBag = $validationBag->withError('host', _('IP address already exists'));

                    return $this->renderer->render($request, 'page::server/edit', [
                        'server'        => $server,
                        'validationBag' => $validationBag,
                    ]);
                }

                $user = $request->getAttribute(UserInterface::class);
                assert($user instanceof UserInterface);

                $this->eventDispatcher->dispatch(new ServerUpdated(
                    $server->id,
                    $update,
                    UserIdentifier::fromString($user->getIdentity()),
                ));

                return new RedirectResponse($this->urlHelper->generate('server'));
            }
        }

        if ($validationBag === null) {
            $validationBag = new ValidationBag(['name' => $server->name, 'host' => $server->host], null, []);
        }

        return $this->renderer->render($request, 'page::server/edit', [
            'server'        => $server,
            'validationBag' => $validationBag,
        ]);
    }

    private function resolveServer(ServerRequestInterface $request): Server|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! ServerIdentifier::isValid($routeId)) {
            return null;
        }

        $activeProject = $request->getAttribute('active_project');
        if (! $activeProject instanceof Project) {
            return null;
        }

        try {
            $server = $this->serverRepository->getById(ServerIdentifier::fromString($routeId));
            assert($server instanceof Server);

            if ($server->projectId->toString() !== $activeProject->id->toString()) {
                return null;
            }

            return $server;
        } catch (Throwable) {
            return null;
        }
    }
}
