<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Dto\Project;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Dto\Project\ProjectCreation;
use Tragwerk\Application\Exception\ValidationCollection;
use Tragwerk\Application\Exception\ValidationError;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_map;
use function preg_match;
use function strlen;

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

    #[Test]
    public function everyProjectGetsItsOwnEntropy(): void
    {
        $dto = new ProjectCreation('Name', $this->serverId(), $this->registryId());

        $first  = $dto->createProject(ProjectIdentifier::create(), TeamIdentifier::create(), UserIdentifier::create());
        $second = $dto->createProject(ProjectIdentifier::create(), TeamIdentifier::create(), UserIdentifier::create());

        self::assertSame(64, strlen($first->entropy));
        self::assertSame(1, preg_match('/^[0-9a-f]{64}$/', $first->entropy));
        self::assertNotSame($first->entropy, $second->entropy);
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
