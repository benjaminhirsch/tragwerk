<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\MapperBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Tragwerk\Domain\Entity\QueueMessage;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\QueueMessageRepository as QueueMessageRepositoryInterface;

use function array_map;
use function time;

final readonly class QueueMessageRepository implements QueueMessageRepositoryInterface
{
    private const string TABLE = 'queue_messages';

    public function __construct(
        private Connection $connection,
        private MapperBuilder $mapperBuilder,
    ) {
    }

    /** @return QueueMessage[] */
    public function getAll(): array
    {
        try {
            $rows = $this->connection->createQueryBuilder()
                ->select('*')
                ->from(self::TABLE)
                ->orderBy('published_at', 'DESC')
                ->executeQuery()
                ->fetchAllAssociative();

            return array_map($this->hydrate(...), $rows);
        } catch (Exception) {
            return [];
        }
    }

    public function getById(string $id): QueueMessage
    {
        try {
            $qb  = $this->connection->createQueryBuilder();
            $row = $qb->select('*')
                ->from(self::TABLE)
                ->where($qb->expr()->eq('id', ':id'))
                ->setParameter('id', $id)
                ->executeQuery()
                ->fetchAssociative();

            if ($row === false) {
                throw EntityNotFound::fromField('id', 'QueueMessage', $id);
            }

            return $this->hydrate($row);
        } catch (EntityNotFound $e) {
            throw $e;
        } catch (MappingError $e) {
            throw EntityHydrationFailed::create(QueueMessage::class, $e);
        } catch (Exception) {
            throw EntityNotFound::fromField('id', 'QueueMessage', $id);
        }
    }

    public function requeue(string $id): void
    {
        try {
            $affected = $this->connection->executeStatement(
                'UPDATE ' . self::TABLE . '
                 SET queue = :queue, delivery_id = NULL, redelivered = :redelivered, published_at = :now
                 WHERE id = :id',
                [
                    'queue'       => 'default',
                    'redelivered' => true,
                    'now'         => time() * 1000,
                    'id'          => $id,
                ],
                [
                    'redelivered' => ParameterType::BOOLEAN,
                ],
            );

            if ($affected === 0) {
                throw EntityNotFound::fromField('id', 'QueueMessage', $id);
            }
        } catch (EntityNotFound $e) {
            throw $e;
        } catch (Exception) {
            throw EntityNotFound::fromField('id', 'QueueMessage', $id);
        }
    }

    public function delete(string $id): void
    {
        try {
            $qb       = $this->connection->createQueryBuilder();
            $affected = $qb->delete(self::TABLE)
                ->where($qb->expr()->eq('id', ':id'))
                ->setParameter('id', $id)
                ->executeStatement();

            if ($affected === 0) {
                throw EntityNotFound::fromField('id', 'QueueMessage', $id);
            }
        } catch (EntityNotFound $e) {
            throw $e;
        } catch (Exception) {
            throw EntityNotFound::fromField('id', 'QueueMessage', $id);
        }
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws MappingError
     */
    private function hydrate(array $row): QueueMessage
    {
        // PostgreSQL returns booleans as 't'/'f' strings via PDO — normalize before mapping.
        $redelivered        = $row['redelivered'];
        $row['redelivered'] = $redelivered === true || $redelivered === 't' || $redelivered === '1';

        return $this->mapperBuilder->mapper()->map(QueueMessage::class, $row);
    }
}
