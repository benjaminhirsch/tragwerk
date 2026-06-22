<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Project;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Project\ProjectUpdate;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

final class ProjectUpdateTest extends TestCase
{
    #[Test]
    public function validInputConstructs(): void
    {
        $dto = new ProjectUpdate(
            'Renamed',
            ServerIdentifier::create()->toString(),
            RegistryIdentifier::create()->toString(),
        );

        self::assertSame('Renamed', $dto->name);
    }

    #[Test]
    public function emptyNameAndBadIdsAreRejected(): void
    {
        try {
            new ProjectUpdate('', 'x', 'y');
        } catch (ValidationCollection $e) {
            $fields = array_map(static fn ($v) => $v->name, $e->validations);
            self::assertContains('name', $fields);
            self::assertContains('serverId', $fields);
            self::assertContains('registryId', $fields);

            return;
        }

        self::fail('Expected ValidationCollection');
    }
}
