<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service;

use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Enum\Locale;
use Tragwerk\Domain\Repository\RecoveryCodeRepository;
use Tragwerk\Domain\Repository\SshKeyRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function iterator_count;
use function iterator_to_array;

/**
 * Assembles the shared data for the account page so every account handler can
 * re-render `page::account/index` (e.g. with a validation bag) without
 * duplicating the data gathering.
 */
final readonly class AccountView
{
    public function __construct(
        private UserRepository $userRepository,
        private SshKeyRepository $sshKeyRepository,
        private RecoveryCodeRepository $recoveryCodeRepository,
    ) {
    }

    /** @return array<non-empty-string, mixed> */
    public function build(UserIdentifier $userId): array
    {
        $user             = $this->userRepository->getById($userId);
        $twoFactorEnabled = $user->hasTwoFactorEnabled();

        return [
            'user'              => $user,
            'keys'              => iterator_to_array($this->sshKeyRepository->getByUserId($userId), false),
            'twoFactorEnabled'  => $twoFactorEnabled,
            'twoFactorSince'    => $twoFactorEnabled ? $user->twoFactorConfirmedAt : null,
            'remainingRecovery' => $twoFactorEnabled
                ? iterator_count($this->recoveryCodeRepository->getActiveByUserId($userId))
                : 0,
            // Seeds the profile form with the current values; POST handlers replace
            // this with the submitted bag (and validation errors) on re-render.
            'profileValidation' => new ValidationBag([
                'firstname' => $user->firstname,
                'lastname'  => $user->lastname,
                'email'     => $user->email,
            ]),
            'languageValidation' => new ValidationBag([
                'locale' => $user->locale instanceof Locale ? $user->locale->value : '',
            ]),
        ];
    }
}
