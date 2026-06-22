<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Registry;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Registry\Registry;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_map;

final class RegistryTest extends TestCase
{
    #[Test]
    public function validInputConstructs(): void
    {
        $dto = new Registry('Hub', 'docker.io', 'acme/app', 'user', 'token');

        self::assertSame('Hub', $dto->name);
        self::assertSame(10, $dto->keepTags);
        self::assertFalse($dto->pruningEnabled);
    }

    #[Test]
    public function keepTagsIsClampedToAtLeastOne(): void
    {
        $entity = (new Registry('Hub', 'docker.io', 'acme/app', 'user', 'token', true, 0))
            ->createRegistry(UserIdentifier::create(), TeamIdentifier::create(), RegistryIdentifier::create());

        self::assertSame(1, $entity->keepTags);
        self::assertTrue($entity->pruningEnabled);
    }

    #[Test]
    public function requiredFieldsAreValidated(): void
    {
        try {
            new Registry('', '', '', '', 'token');
        } catch (ValidationCollection $e) {
            $fields = array_map(static fn (ValidationError $v): string => $v->name, $e->validations);
            self::assertContains('name', $fields);
            self::assertContains('url', $fields);
            self::assertContains('repository', $fields);
            self::assertContains('username', $fields);

            return;
        }

        self::fail('Expected ValidationCollection');
    }
}
