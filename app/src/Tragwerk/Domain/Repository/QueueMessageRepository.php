<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\Entity\QueueMessage;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;

interface QueueMessageRepository
{
    /** @return QueueMessage[] */
    public function getAll(): array;

    /** @throws EntityNotFound */
    public function getById(string $id): QueueMessage;

    /** @throws EntityNotFound */
    public function requeue(string $id): void;

    /** @throws EntityNotFound */
    public function delete(string $id): void;
}
