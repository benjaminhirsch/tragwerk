<?php

declare(strict_types=1);

namespace Tragwerk\Infrastructure\Repository;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Doctrine\DBAL;
use Doctrine\DBAL\Connection;
use JsonException;
use Tragwerk\Application\Helper\CaseConverter;
use Tragwerk\Domain\Entity\Entity;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\ValueObject\EntityIdentifier;
use Tragwerk\Infrastructure\Helper\EntityHelper;

use function assert;
use function is_array;
use function is_bool;
use function is_string;

abstract class GenericRepository
{
    public function __construct(
        protected Connection $connection,
        protected MapperBuilder $mapperBuilder,
        protected NormalizerBuilder $normalizerBuilder,
    ) {
    }

    public function getById(EntityIdentifier $id): Entity
    {
        $qb   = $this->connection->createQueryBuilder();
        $stmt = $qb
            ->select('*')
            ->from(EntityHelper::getDbTableName($id::getEntityType()))
            ->where($qb->expr()->eq('id', ':id'));
        $stmt->setParameter('id', $id);
        $targetClass = $id::getEntityType()->getEntityClassName();

        try {
            $row = $stmt->fetchAssociative();
            if ($row === false) {
                throw EntityNotFound::fromIdentifier($id);
            }

            $entity = $this->map($row, $targetClass);
            assert($entity instanceof Entity);

            return $entity;
        } catch (DBAL\Exception) {
            throw EntityNotFound::fromIdentifier($id);
        } catch (MappingError | JsonException $e) {
            throw EntityHydrationFailed::create($targetClass, $e);
        }
    }

    public function create(Entity $entity): void
    {
        try {
            $data = $this->normalizerBuilder->normalizer(Format::array())->normalize($entity);
            assert(is_array($data));
            $this->connection->insert(
                EntityHelper::getDbTableName($entity->id::getEntityType()),
                // @phpstan-ignore argument.type
                CaseConverter::camelToSnakeCase($this->normalizeBooleans($data)),
            );
        } catch (DBAL\Exception $e) {
            throw EntityCreationFailed::create($entity::class, $entity->id, $e);
        }
    }

    public function update(Entity $entity): void
    {
        $qb = $this->connection->createQueryBuilder();
        $qb = $qb->update(EntityHelper::getDbTableName($entity->id::getEntityType()))->where($qb->expr()->eq(
            'id',
            ':id',
        ));

        $normalizedData = $this->normalizerBuilder->normalizer(Format::array())->normalize($entity);

        assert(is_array($normalizedData));
        $data = CaseConverter::camelToSnakeCase($this->normalizeBooleans($normalizedData));

        foreach ($data as $key => $value) {
            assert(is_string($key));
            $qb = $qb->set($key, ':' . $key);
        }

        $qb->setParameters($data);

        try {
            $row = $qb->executeStatement();
            if ($row === 0) {
                throw EntityNotFound::fromIdentifier($entity->id);
            }
        } catch (DBAL\Exception $e) {
            throw EntityUpdateFailed::create($entity->id, $e);
        }
    }

    public function delete(EntityIdentifier $id): void
    {
        $qb = $this->connection->createQueryBuilder();
        try {
            $affectedRows = $qb
                ->delete(EntityHelper::getDbTableName($id::getEntityType()))
                ->where($qb->expr()->eq('id', ':id'))
                ->setParameter('id', $id)
                ->executeStatement();

            if ($affectedRows === 0) {
                throw EntityNotFound::fromIdentifier($id);
            }
        } catch (DBAL\Exception $e) {
            throw EntityDeletionFailed::create($id, $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param class-string<T>      $className
     *
     * @return T
     *
     * @throws MappingError|JsonException
     *
     * @template T of object
     */
    protected function map(array $data, string $className): object
    {
        return $this->mapperBuilder->mapper()->map($className, $data);
    }

    /**
     * @param mixed[] $data
     *
     * @return mixed[]
     */
    private function normalizeBooleans(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $data[$key] = (int) $value;
            } elseif (is_array($value)) {
                $data[$key] = $this->normalizeBooleans($value);
            }
        }

        return $data;
    }
}
