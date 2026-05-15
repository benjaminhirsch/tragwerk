<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Valinor;

use CuyZ\Valinor\NormalizerBuilder;
use Tragwerk\Domain\ValueObject\EntityIdentifier;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

final class DefaultNormalizeBuilderFactory
{
    public function __invoke(): NormalizerBuilder
    {
        return new NormalizerBuilder()
            ->registerTransformer(
                static fn (PasswordHash $ts) => $ts->toString(),
            )
            ->registerTransformer(
                static fn (TimestampImmutable $ts) => $ts->toString(),
            )
            ->registerTransformer(
                static fn (EntityIdentifier $id) => $id->toString(),
            );
    }
}
