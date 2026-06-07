<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Ramsey\Uuid\Uuid;
use Tragwerk\Domain\Repository\RegistryPrefixRepository as RegistryPrefixRepositoryInterface;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;

use function is_array;
use function is_string;

final readonly class RegistryPrefixRepository implements RegistryPrefixRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    #[Override]
    public function upsert(
        ProjectIdentifier $projectId,
        RegistryIdentifier $registryId,
        string $appSlug,
        string $branchSlug,
    ): void {
        try {
            $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO registry_prefixes (id, project_id, registry_id, app_slug, branch_slug)
                    VALUES (:id, :project_id, :registry_id, :app_slug, :branch_slug)
                    ON CONFLICT (project_id, app_slug, branch_slug) DO NOTHING
                SQL,
                [
                    'id'          => Uuid::uuid4()->toString(),
                    'project_id'  => $projectId->toString(),
                    'registry_id' => $registryId->toString(),
                    'app_slug'    => $appSlug,
                    'branch_slug' => $branchSlug,
                ],
            );
        } catch (Exception) {
        }
    }

    /** @return list<array{registry_id: string, app_slug: string, branch_slug: string}> */
    #[Override]
    public function findByProject(ProjectIdentifier $projectId): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT registry_id, app_slug, branch_slug FROM registry_prefixes WHERE project_id = :id',
                ['id' => $projectId->toString()],
            );
        } catch (Exception) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (
                ! is_array($row)
                || ! is_string($row['registry_id'] ?? null)
                || ! is_string($row['app_slug'] ?? null)
                || ! is_string($row['branch_slug'] ?? null)
            ) {
                continue;
            }

            $result[] = [
                'registry_id' => $row['registry_id'],
                'app_slug'    => $row['app_slug'],
                'branch_slug' => $row['branch_slug'],
            ];
        }

        return $result;
    }

    /** @return list<array{app_slug: string, branch_slug: string}> */
    #[Override]
    public function findByRegistry(RegistryIdentifier $registryId): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT app_slug, branch_slug FROM registry_prefixes WHERE registry_id = :id',
                ['id' => $registryId->toString()],
            );
        } catch (Exception) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['app_slug'] ?? null) || ! is_string($row['branch_slug'] ?? null)) {
                continue;
            }

            $result[] = ['app_slug' => $row['app_slug'], 'branch_slug' => $row['branch_slug']];
        }

        return $result;
    }
}
