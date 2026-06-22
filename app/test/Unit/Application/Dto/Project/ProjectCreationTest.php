<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Project;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Project\ProjectCreation;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;

use function array_map;

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
        $fields = $this->errorFields('', $this->serverId(), $this->registryId());

        self::assertContains('name', $fields);
    }

    #[Test]
    public function invalidServerIdIsRejected(): void
    {
        $fields = $this->errorFields('Name', 'not-a-uuid', $this->registryId());

        self::assertContains('serverId', $fields);
    }

    #[Test]
    public function invalidRegistryIdIsRejected(): void
    {
        $fields = $this->errorFields('Name', $this->serverId(), '');

        self::assertContains('registryId', $fields);
    }

    private function serverId(): string
    {
        return ServerIdentifier::create()->toString();
    }

    private function registryId(): string
    {
        return RegistryIdentifier::create()->toString();
    }

    /** @return array<string> */
    private function errorFields(string $name, string $serverId, string $registryId): array
    {
        try {
            new ProjectCreation($name, $serverId, $registryId);
        } catch (ValidationCollection $e) {
            return array_map(static fn (ValidationError $v): string => $v->name, $e->validations);
        }

        self::fail('Expected ValidationCollection');
    }
}
