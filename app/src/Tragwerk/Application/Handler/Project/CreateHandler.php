<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\Project;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Event\ProjectCreated;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;

final readonly class CreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $validationBag = null;
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, Project\ProjectCreation::class);

            $user = $request->getAttribute(UserInterface::class);
            assert($user instanceof UserInterface);

            if (! $validationBag->hasErrors()) {
                $creation = $validationBag->getDto();
                assert($creation instanceof Project\ProjectCreation);
                $this->eventDispatcher->dispatch(new ProjectCreated(
                    $creation,
                    UserIdentifier::fromString($user->getIdentity()),
                ));

                return new RedirectResponse($this->urlHelper->generate('project'));
            }
        }

        return $this->renderer->render($request, 'page::project/create', ['validationBag' => $validationBag]);
    }
}
