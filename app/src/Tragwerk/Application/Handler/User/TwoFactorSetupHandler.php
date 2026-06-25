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
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\TwoFactor\TwoFactorService;
use Tragwerk\Domain\Entity\UserTwoFactor;
use Tragwerk\Domain\Event\TwoFactorEnrollmentStarted;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\Repository\UserTwoFactorRepository;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Domain\ValueObject\UserTwoFactorIdentifier;

use function assert;
use function chunk_split;
use function rtrim;

final readonly class TwoFactorSetupHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private UserRepository $userRepository,
        private UserTwoFactorRepository $userTwoFactorRepository,
        private TwoFactorService $twoFactorService,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);
        $userId = UserIdentifier::fromString($user->getIdentity());

        $entity = $this->userRepository->getById($userId);
        if ($entity->hasTwoFactorEnabled()) {
            return new RedirectResponse($this->urlHelper->generate('account'));
        }

        // Reuse an in-progress (unconfirmed) enrollment so the displayed secret
        // is stable across reloads; otherwise start a fresh one.
        $enrollment = $this->userTwoFactorRepository->findByUserId($userId);
        if ($enrollment === null) {
            $secret     = $this->twoFactorService->generateSecret();
            $enrollment = new UserTwoFactor(
                id: UserTwoFactorIdentifier::create(),
                userId: $userId,
                secret: $this->twoFactorService->encryptSecret($secret),
                createdAt: TimestampImmutable::now(),
                updatedAt: TimestampImmutable::now(),
            );

            $this->eventDispatcher->dispatch(new TwoFactorEnrollmentStarted($enrollment));
        } else {
            $secret = $this->twoFactorService->decryptSecret($enrollment->secret);
        }

        $provisioningUri = $this->twoFactorService->provisioningUri($secret, $entity->email);
        $queryParams     = $request->getQueryParams();

        return $this->renderer->render($request, 'page::account/setup', [
            'qrCode'        => $this->twoFactorService->qrCodeDataUri($provisioningUri),
            'secret'        => rtrim(chunk_split($secret, 4, ' ')),
            'codeError'     => isset($queryParams['invalid-code']),
        ]);
    }
}
