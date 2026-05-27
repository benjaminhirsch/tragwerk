<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Generator;
use Tragwerk\Domain\Entity\SshKey;
use Tragwerk\Domain\ValueObject\SshKeyIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

interface SshKeyRepository
{
    public function create(SshKey $key): void;

    public function delete(SshKeyIdentifier $id): void;

    /** @return Generator<SshKey> */
    public function getByUserId(UserIdentifier $userId): Generator;

    /** @return Generator<SshKey> */
    public function getAll(): Generator;
}
