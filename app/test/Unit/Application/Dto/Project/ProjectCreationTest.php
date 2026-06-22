<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Project;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Project\ProjectCreation;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

final class ProjectCreationTest extends TestCase
{
    #[Test]
    public function validInputConstructs(): void
    {
        $dto = new ProjectCreation(
            'My Project',
            ServerIdentifier::create()->toString(),
            RegistryIdentifier::create()->toString(),
        );

        self::assertSame('My Project', $dto->name);
    }

    #[Test]
    public function emptyNameIsRejected(): void
    {
        self::assertContains('name', $this->errorFields('', ServerIdentifier::create()->toString(), RegistryIdentifier::create()->toString()));
    }

    #[Test]
    public function invalidServerIdIsRejected(): void
    {
        self::assertContains('serverId', $this->errorFields('Name', 'not-a-uuid', RegistryIdentifier::create()->toString()));
    }

    #[Test]
    public function invalidRegistryIdIsRejected(): void
    {
        self::assertContains('registryId', $this->errorFields('Name', ServerIdentifier::create()->toString(), ''));
    }

    /** @return list<string> */
    private function errorFields(string $name, string $serverId, string $registryId): array
    {
        try {
            new ProjectCreation($name, $serverId, $registryId);
        } catch (ValidationCollection $e) {
            return array_map(static fn ($v) => $v->name, $e->validations);
        }

        self::fail('Expected ValidationCollection');
    }
}
