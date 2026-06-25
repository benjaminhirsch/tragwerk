<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\User;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\ConfirmPassword;
use Tragwerk\Application\Helper\TrustedDeviceCookie;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Domain\Event\TwoFactorDisabled;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;

final readonly class TwoFactorDisableHandler implements RequestHandlerInterface
{
    public function __construct(
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private UserRepository $userRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);
        $userId = UserIdentifier::fromString($user->getIdentity());

        $entity = $this->userRepository->getById($userId);
        if (! $entity->hasTwoFactorEnabled()) {
            return new RedirectResponse($this->urlHelper->generate('account'));
        }

        $validationBag = $this->mapper->mapAndValidate($request, ConfirmPassword::class);
        $dto           = $validationBag->getDto();

        $passwordValid = $dto instanceof ConfirmPassword && $entity->password->verify($dto->password);
        if ($validationBag->hasErrors() || ! $passwordValid) {
            return new RedirectResponse($this->urlHelper->generate('account') . '?disable-error=1');
        }

        $this->eventDispatcher->dispatch(new TwoFactorDisabled($userId));

        // Drop the trusted-device cookie on this browser as well.
        return TrustedDeviceCookie::withClearedCookie(
            new RedirectResponse($this->urlHelper->generate('account')),
        );
    }
}
