<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\User;

use Mezzio\Authentication\UserInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Repository\RecoveryCodeRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function iterator_count;

final readonly class AccountHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private UserRepository $userRepository,
        private RecoveryCodeRepository $recoveryCodeRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);
        $userId = UserIdentifier::fromString($user->getIdentity());

        $entity            = $this->userRepository->getById($userId);
        $twoFactorEnabled  = $entity->hasTwoFactorEnabled();
        $remainingRecovery = $twoFactorEnabled
            ? iterator_count($this->recoveryCodeRepository->getActiveByUserId($userId))
            : 0;

        $queryParams = $request->getQueryParams();

        return $this->renderer->render($request, 'page::account/index', [
            'user'              => $entity,
            'twoFactorEnabled'  => $twoFactorEnabled,
            'twoFactorSince'    => $twoFactorEnabled ? $entity->twoFactorConfirmedAt : null,
            'remainingRecovery' => $remainingRecovery,
            'disableError'      => isset($queryParams['disable-error']),
            'enabled'           => isset($queryParams['enabled']),
        ]);
    }
}
