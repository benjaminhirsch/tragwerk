<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Server;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\Server\Server as ServerDto;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Event\ServerCreated;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function assert;

final readonly class CreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private ServerRepository $serverRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $validationBag = null;
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, ServerDto::class);

            $user = $request->getAttribute(UserInterface::class);
            assert($user instanceof UserInterface);

            $activeProject = $request->getAttribute('active_project');
            assert($activeProject instanceof Project);

            if (! $validationBag->hasErrors()) {
                $registration = $validationBag->getDto();
                assert($registration instanceof ServerDto);

                if (! $this->serverRepository->existsByHost($registration->host)) {
                    $this->eventDispatcher->dispatch(new ServerCreated(
                        $registration,
                        UserIdentifier::fromString($user->getIdentity()),
                        $activeProject->id,
                    ));

                    return new RedirectResponse($this->urlHelper->generate('server'));
                }

                $validationBag = $validationBag->withError('host', _('IP address is already in use'));
            }
        }

        return $this->renderer->render($request, 'page::server/create', ['validationBag' => $validationBag]);
    }
}
