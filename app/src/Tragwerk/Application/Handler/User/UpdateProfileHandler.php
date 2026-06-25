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
use Tragwerk\Application\Dto\UpdateProfile;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\AccountView;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Event\EmailChangeRequested;
use Tragwerk\Domain\Event\UserProfileUpdated;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function array_merge;
use function assert;

final readonly class UpdateProfileHandler implements RequestHandlerInterface
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

        $validationBag = $this->mapper->mapAndValidate($request, UpdateProfile::class);
        $dto           = $validationBag->getDto();

        if ($validationBag->hasErrors() || ! $dto instanceof UpdateProfile) {
            return $this->reRender($request, $userId, $validationBag);
        }

        $emailChanged = $dto->email !== $entity->email;
        if ($emailChanged && $this->emailTaken($dto->email)) {
            return $this->reRender(
                $request,
                $userId,
                $validationBag->withError('email', _('This email address is already in use')),
            );
        }

        $this->eventDispatcher->dispatch(new UserProfileUpdated($userId, $dto->firstname, $dto->lastname));

        if ($emailChanged) {
            $this->eventDispatcher->dispatch(new EmailChangeRequested($entity, $dto->email));

            return new RedirectResponse($this->urlHelper->generate('account') . '?email-pending=1#profile');
        }

        return new RedirectResponse($this->urlHelper->generate('account') . '?profile-saved=1#profile');
    }

    private function emailTaken(string $email): bool
    {
        try {
            $this->userRepository->getByEmail($email);

            return true;
        } catch (EntityNotFound) {
            return false;
        }
    }

    private function reRender(
        ServerRequestInterface $request,
        UserIdentifier $userId,
        ValidationBag $validationBag,
    ): ResponseInterface {
        return $this->renderer->render($request, 'page::account/index', array_merge(
            $this->accountView->build($userId),
            ['profileValidation' => $validationBag],
        ));
    }
}
