<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use Doctrine\DBAL\Exception;
use Override;
use Tragwerk\Domain\Entity\ProjectWebhook;
use Tragwerk\Domain\Enum\EntityType;
use Tragwerk\Domain\Enum\GitForge;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Repository\ProjectWebhookRepository as ProjectWebhookRepositoryInterface;
use Tragwerk\Domain\ValueObject\EntityIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function assert;

final class ProjectWebhookRepository extends GenericRepository implements ProjectWebhookRepositoryInterface
{
    #[Override]
    public function getById(EntityIdentifier $id): ProjectWebhook
    {
        $entity = parent::getById($id);
        assert($entity instanceof ProjectWebhook);

        return $entity;
    }

    #[Override]
    public function findByProject(ProjectIdentifier $projectId): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::WEBHOOK_INTEGRATION))
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->setParameter('project_id', $projectId->toString())
            ->orderBy('created_at', 'ASC');

        try {
            $integrations = [];
            foreach ($qb->executeQuery()->iterateAssociative() as $row) {
                $integrations[] = $this->map($row, ProjectWebhook::class);
            }

            return $integrations;
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(ProjectWebhook::class, $e);
        }
    }

    #[Override]
    public function findByProjectAndForge(ProjectIdentifier $projectId, GitForge $forge): ProjectWebhook|null
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('*')
            ->from(EntityHelper::getDbTableName(EntityType::WEBHOOK_INTEGRATION))
            ->where($qb->expr()->eq('project_id', ':project_id'))
            ->andWhere($qb->expr()->eq('forge', ':forge'))
            ->setParameter('project_id', $projectId->toString())
            ->setParameter('forge', $forge->value);

        try {
            $row = $qb->executeQuery()->fetchAssociative();
            if ($row === false) {
                return null;
            }

            return $this->map($row, ProjectWebhook::class);
        } catch (MappingError | Exception $e) {
            throw EntityHydrationFailed::create(ProjectWebhook::class, $e);
        }
    }
}
