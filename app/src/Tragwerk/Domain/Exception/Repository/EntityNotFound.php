<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Exception\Repository;

use Override;
use Tragwerk\Domain\ValueObject\EntityIdentifier;

use function sprintf;

final class EntityNotFound extends RecordNotFound
{
    /** @psalm-pure */
    public static function fromIdentifier(EntityIdentifier $identifier): self
    {
        return self::fromField('id', $identifier::getEntityType()->value, $identifier->toString());
    }

    /** @psalm-pure */
    #[Override]
    public static function fromField(string $fieldName, string $type, string $identifier): self
    {
        return new self(sprintf('Entity of type %s with %s %s not found', $type, $fieldName, $identifier));
    }
}
