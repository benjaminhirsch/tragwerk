<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\UserRegistration;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Event\UserRegistered;

use function assert;

final readonly class UserRegisterHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $userRegistrationMapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $validationBag = null;
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->userRegistrationMapper->mapAndValidate($request, UserRegistration::class);

            if (! $validationBag->hasErrors()) {
                $registration = $validationBag->getDto();
                assert($registration instanceof UserRegistration);
                $this->eventDispatcher->dispatch(new UserRegistered($registration->createUser()));

                return new RedirectResponse($this->urlHelper->generate('login'));
            }
        }

        return $this->renderer->render($request, 'page::register', ['validationBag' => $validationBag]);
    }
}
