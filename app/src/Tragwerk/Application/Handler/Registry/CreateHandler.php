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
use Tragwerk\Application\Dto\Registry\Registry as RegistryDto;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\RegistryCreated;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function assert;
use function trim;

final readonly class CreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, RegistryDto::class);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof RegistryDto);

                if (trim($dto->password) === '') {
                    $validationBag = $validationBag->withError('password', _('Field can\'t be empty'));
                }
            }

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof RegistryDto);

                $user = $request->getAttribute(UserInterface::class);
                assert($user instanceof UserInterface);

                $activeTeam = $request->getAttribute('active_team');
                assert($activeTeam instanceof Team);

                $registryId = RegistryIdentifier::create();

                $this->eventDispatcher->dispatch(new RegistryCreated(
                    $dto,
                    UserIdentifier::fromString($user->getIdentity()),
                    $activeTeam->id,
                    $registryId,
                ));

                return new RedirectResponse($this->urlHelper->generate('registry'));
            }
        }

        return $this->renderer->render($request, 'page::registry/create', ['validationBag' => $validationBag]);
    }
}
