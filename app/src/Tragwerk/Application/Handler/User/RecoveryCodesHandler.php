<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\User;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\ConfirmPassword;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\TwoFactor\TwoFactorService;
use Tragwerk\Domain\Entity\RecoveryCode;
use Tragwerk\Domain\Event\RecoveryCodesGenerated;
use Tragwerk\Domain\Repository\RecoveryCodeRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\RecoveryCodeIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_map;
use function assert;
use function iterator_count;

final readonly class RecoveryCodesHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private UserRepository $userRepository,
        private RecoveryCodeRepository $recoveryCodeRepository,
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
        if (! $entity->hasTwoFactorEnabled()) {
            return new RedirectResponse($this->urlHelper->generate('account'));
        }

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            return $this->handleRegenerate($request, $userId, $entity->password->verify(...));
        }

        $remaining = iterator_count($this->recoveryCodeRepository->getActiveByUserId($userId));

        return $this->renderer->render($request, 'page::account/recovery-codes-info', [
            'remaining'    => $remaining,
            'passwordError' => false,
        ]);
    }

    /** @param callable(string): bool $verifyPassword */
    private function handleRegenerate(
        ServerRequestInterface $request,
        UserIdentifier $userId,
        callable $verifyPassword,
    ): ResponseInterface {
        $validationBag = $this->mapper->mapAndValidate($request, ConfirmPassword::class);
        $dto           = $validationBag->getDto();

        if ($validationBag->hasErrors() || ! $dto instanceof ConfirmPassword || ! $verifyPassword($dto->password)) {
            $remaining = iterator_count($this->recoveryCodeRepository->getActiveByUserId($userId));

            return $this->renderer->render($request, 'page::account/recovery-codes-info', [
                'remaining'    => $remaining,
                'passwordError' => true,
            ]);
        }

        $plaintextCodes = $this->twoFactorService->generateRecoveryCodes();
        $recoveryCodes  = array_map(
            fn (string $code): RecoveryCode => new RecoveryCode(
                id: RecoveryCodeIdentifier::create(),
                userId: $userId,
                codeHash: $this->twoFactorService->hashRecoveryCode($code),
                createdAt: TimestampImmutable::now(),
            ),
            $plaintextCodes,
        );

        $this->eventDispatcher->dispatch(new RecoveryCodesGenerated($userId, $recoveryCodes));

        return $this->renderer->render($request, 'page::account/recovery-codes', [
            'codes'       => $plaintextCodes,
            'justEnabled' => false,
        ]);
    }
}
