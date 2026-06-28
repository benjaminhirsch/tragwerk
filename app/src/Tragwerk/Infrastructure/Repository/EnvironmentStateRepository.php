<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Override;
use Tragwerk\Domain\Repository\EnvironmentStateRepository as EnvironmentStateRepositoryInterface;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function is_string;

final readonly class EnvironmentStateRepository implements EnvironmentStateRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    #[Override]
    public function disable(ProjectIdentifier $projectId, string $branch): void
    {
        try {
            $this->connection->executeStatement(
                <<<'SQL'
                    INSERT INTO disabled_environments (project_id, branch, disabled_at)
                    VALUES (:project_id, :branch, :disabled_at)
                    ON CONFLICT (project_id, branch) DO UPDATE SET disabled_at = EXCLUDED.disabled_at
                SQL,
                [
                    'project_id'  => $projectId->toString(),
                    'branch'      => $branch,
                    'disabled_at' => (string) TimestampImmutable::now(),
                ],
            );
        } catch (Exception) {
            // best-effort state tracking
        }
    }

    #[Override]
    public function enable(ProjectIdentifier $projectId, string $branch): void
    {
        try {
            $this->connection->executeStatement(
                'DELETE FROM disabled_environments WHERE project_id = :project_id AND branch = :branch',
                ['project_id' => $projectId->toString(), 'branch' => $branch],
            );
        } catch (Exception) {
            // best-effort state tracking
        }
    }

    #[Override]
    public function isDisabled(ProjectIdentifier $projectId, string $branch): bool
    {
        try {
            $found = $this->connection->fetchOne(
                'SELECT 1 FROM disabled_environments WHERE project_id = :project_id AND branch = :branch',
                ['project_id' => $projectId->toString(), 'branch' => $branch],
            );
        } catch (Exception) {
            return false;
        }

        return $found !== false;
    }

    /**
     * @param string[] $branches
     *
     * @return array<string, bool>
     */
    #[Override]
    public function disabledMap(ProjectIdentifier $projectId, array $branches): array
    {
        if ($branches === []) {
            return [];
        }

        try {
            $rows = $this->connection->fetchFirstColumn(
                'SELECT branch FROM disabled_environments WHERE project_id = :project_id AND branch IN (:branches)',
                ['project_id' => $projectId->toString(), 'branches' => $branches],
                ['branches' => ArrayParameterType::STRING],
            );
        } catch (Exception) {
            return [];
        }

        $result = [];
        foreach ($rows as $branch) {
            if (! is_string($branch)) {
                continue;
            }

            $result[$branch] = true;
        }

        return $result;
    }
}
