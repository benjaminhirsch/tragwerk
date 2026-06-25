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
use Tragwerk\Application\Dto\ChangePassword;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\AccountView;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Event\UserPasswordChanged;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function array_merge;
use function assert;

final readonly class ChangePasswordHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private UserRepository $userRepository,
        private AccountView $accountView,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);
        $userId = UserIdentifier::fromString($user->getIdentity());
        $entity = $this->userRepository->getById($userId);

        $validationBag = $this->mapper->mapAndValidate($request, ChangePassword::class);
        $dto           = $validationBag->getDto();

        if ($validationBag->hasErrors() || ! $dto instanceof ChangePassword) {
            return $this->reRender($request, $userId, $validationBag);
        }

        if (! $entity->password->verify($dto->currentPassword)) {
            return $this->reRender(
                $request,
                $userId,
                $validationBag->withError('currentPassword', _('Your current password is incorrect')),
            );
        }

        $this->eventDispatcher->dispatch(
            new UserPasswordChanged($userId, (string) PasswordHash::create($dto->newPassword)),
        );

        return new RedirectResponse($this->urlHelper->generate('account') . '?password-changed=1#password');
    }

    private function reRender(
        ServerRequestInterface $request,
        UserIdentifier $userId,
        ValidationBag $validationBag,
    ): ResponseInterface {
        return $this->renderer->render($request, 'page::account/index', array_merge(
            $this->accountView->build($userId),
            ['passwordValidation' => $validationBag],
        ));
    }
}
