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
use Tragwerk\Application\Dto\TwoFactorEnable;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\TwoFactor\TwoFactorService;
use Tragwerk\Domain\Entity\RecoveryCode;
use Tragwerk\Domain\Event\RecoveryCodesGenerated;
use Tragwerk\Domain\Event\TwoFactorEnabled;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\Repository\UserTwoFactorRepository;
use Tragwerk\Domain\ValueObject\RecoveryCodeIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_map;
use function assert;

final readonly class TwoFactorEnableHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
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

        $enrollment = $this->userTwoFactorRepository->findByUserId($userId);
        if ($enrollment === null) {
            return new RedirectResponse($this->urlHelper->generate('2fa.setup'));
        }

        $validationBag = $this->mapper->mapAndValidate($request, TwoFactorEnable::class);
        if ($validationBag->hasErrors()) {
            return $this->backToSetupWithError();
        }

        $dto = $validationBag->getDto();
        assert($dto instanceof TwoFactorEnable);

        $secret = $this->twoFactorService->decryptSecret($enrollment->secret);
        if (! $this->twoFactorService->verify($secret, $dto->code)) {
            return $this->backToSetupWithError();
        }

        $this->eventDispatcher->dispatch(new TwoFactorEnabled($userId));

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
            'codes'     => $plaintextCodes,
            'justEnabled' => true,
        ]);
    }

    private function backToSetupWithError(): ResponseInterface
    {
        return new RedirectResponse($this->urlHelper->generate('2fa.setup') . '?invalid-code=1');
    }
}
