<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Credential;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\Credential\Credential as CredentialDto;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Event\CredentialCreated;
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
            $validationBag = $this->mapper->mapAndValidate($request, CredentialDto::class);

            $user = $request->getAttribute(UserInterface::class);
            assert($user instanceof UserInterface);

            $activeProject = $request->getAttribute('active_project');
            assert($activeProject instanceof Project);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof CredentialDto);

                $this->eventDispatcher->dispatch(new CredentialCreated(
                    $dto,
                    UserIdentifier::fromString($user->getIdentity()),
                    $activeProject->id,
                ));

                return new RedirectResponse($this->urlHelper->generate('credential'));
            }
        }

        return $this->renderer->render($request, 'page::credential/create', ['validationBag' => $validationBag]);
    }
}
