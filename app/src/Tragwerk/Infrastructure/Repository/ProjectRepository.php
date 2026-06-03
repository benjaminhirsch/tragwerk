<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use Generator;
use Override;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\SwarmNode;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Enum\SwarmNodeRole;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\ProjectRepository as ProjectRepositoryInterface;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function array_map;
use function assert;
use function is_int;
use function is_string;

final class ProjectRepository extends GenericRepository implements ProjectRepositoryInterface
{
    #[Override]
    public function isServerInUse(ServerIdentifier $serverId, ProjectIdentifier|null $excludeProjectId = null): bool
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('COUNT(*)')
            ->from(EntityHelper::getDbTableName(EntityType::PROJECT))
            ->where($qb->expr()->eq('server_id', ':server_id'))
            ->setParameter('server_id', $serverId->toString());

        if ($excludeProjectId !== null) {
            $qb->andWhere($qb->expr()->neq('id', ':exclude_id'))
                ->setParameter('exclude_id', $excludeProjectId->toString());
        }

        $result = $qb->executeQuery()->fetchOne();

        return (is_string($result) || is_int($result)) && (int) $result > 0;
    }

    #[Override]
    public function getAll(TeamIdentifier|null $teamId = null): Generator
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')->from(EntityHelper::getDbTableName(EntityType::PROJECT));

        if ($teamId !== null) {
            $qb->andWhere($qb->expr()->eq('team_id', ':team_id'));
            $qb->setParameter('team_id', $teamId->toString());
        }

        try {
            foreach ($qb->addOrderBy('created_at', 'DESC')->executeQuery()->iterateAssociative() as $row) {
                yield $this->map($row, Project::class);
            }
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(Project::class, $e);
        }
    }

    #[Override]
    public function addSwarmNode(SwarmNode $node): void
    {
        try {
            $this->connection->insert('project_swarm_nodes', [
                'project_id' => $node->projectId->toString(),
                'server_id'  => $node->serverId->toString(),
                'role'       => $node->role->value,
                'is_storage' => (int) $node->isStorage,
            ]);
        } catch (Exception $e) {
            throw EntityCreationFailed::create(SwarmNode::class, $node->projectId, $e);
        }
    }

    #[Override]
    public function removeSwarmNode(ProjectIdentifier $projectId, ServerIdentifier $serverId): void
    {
        try {
            $affected = $this->connection->delete('project_swarm_nodes', [
                'project_id' => $projectId->toString(),
                'server_id'  => $serverId->toString(),
            ]);

            if ($affected === 0) {
                throw EntityNotFound::fromIdentifier($projectId);
            }
        } catch (Exception $e) {
            throw EntityDeletionFailed::create($projectId, $e);
        }
    }

    #[Override]
    public function getSwarmNodes(ProjectIdentifier $projectId): array
    {
        $qb   = $this->connection->createQueryBuilder();
        $rows = $qb
            ->select('*')
            ->from('project_swarm_nodes')
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->setParameter('project_id', $projectId->toString())
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map($this->hydrateSwarmNode(...), $rows);
    }

    #[Override]
    public function getSwarmStorageNode(ProjectIdentifier $projectId): SwarmNode|null
    {
        $qb  = $this->connection->createQueryBuilder();
        $row = $qb
            ->select('*')
            ->from('project_swarm_nodes')
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('is_storage', ':is_storage'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('is_storage', 1)
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return $this->hydrateSwarmNode($row);
    }

    #[Override]
    public function swarmNodeExists(ProjectIdentifier $projectId, ServerIdentifier $serverId): bool
    {
        $qb     = $this->connection->createQueryBuilder();
        $result = $qb
            ->select('COUNT(*)')
            ->from('project_swarm_nodes')
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('server_id', ':server_id'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('server_id', $serverId->toString())
            ->executeQuery()
            ->fetchOne();

        return (is_string($result) || is_int($result)) && (int) $result > 0;
    }

    #[Override]
    public function isServerInSwarmCluster(
        ServerIdentifier $serverId,
        ProjectIdentifier|null $excludeProjectId = null,
    ): bool {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('COUNT(*)')
            ->from('project_swarm_nodes')
            ->where($qb->expr()->eq('server_id', ':server_id'))
            ->setParameter('server_id', $serverId->toString());

        if ($excludeProjectId !== null) {
            $qb->andWhere($qb->expr()->neq('project_id', ':exclude_id'))
                ->setParameter('exclude_id', $excludeProjectId->toString());
        }

        $result = $qb->executeQuery()->fetchOne();
        if ((is_string($result) || is_int($result)) && (int) $result > 0) {
            return true;
        }

        return $this->isServerInUse($serverId, $excludeProjectId);
    }

    /** @param array<string, mixed> $row */
    private function hydrateSwarmNode(array $row): SwarmNode
    {
        assert(is_string($row['project_id']));
        assert(is_string($row['server_id']));
        assert(is_string($row['role']));

        return new SwarmNode(
            projectId: ProjectIdentifier::fromString($row['project_id']),
            serverId:  ServerIdentifier::fromString($row['server_id']),
            role:      SwarmNodeRole::from($row['role']),
            isStorage: (bool) $row['is_storage'],
        );
    }
}
