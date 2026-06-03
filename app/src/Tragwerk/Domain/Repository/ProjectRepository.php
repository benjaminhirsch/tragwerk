<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Generator;
use Tragwerk\Domain\Entity\Entity;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\SwarmNode;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityHydrationFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Exception\Repository\EntityUpdateFailed;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;

interface ProjectRepository
{
    /**
     * @return Project
     *
     * @throws EntityCreationFailed
     * @throws EntityHydrationFailed
     * @throws EntityNotFound
     */
    public function getById(ProjectIdentifier $id): Entity;

    /** @throws EntityCreationFailed */
    public function create(Project $entity): void;

    /** @throws EntityUpdateFailed */
    public function update(Project $entity): void;

    /** @throws EntityDeletionFailed */
    public function delete(ProjectIdentifier $id): void;

    /** @return Generator<Project> */
    public function getAll(TeamIdentifier|null $teamId = null): Generator;

    public function isServerInUse(ServerIdentifier $serverId, ProjectIdentifier|null $excludeProjectId = null): bool;

    /** @throws EntityCreationFailed */
    public function addSwarmNode(SwarmNode $node): void;

    /** @throws EntityDeletionFailed */
    public function removeSwarmNode(ProjectIdentifier $projectId, ServerIdentifier $serverId): void;

    /** @return list<SwarmNode> */
    public function getSwarmNodes(ProjectIdentifier $projectId): array;

    public function getSwarmStorageNode(ProjectIdentifier $projectId): SwarmNode|null;

    public function swarmNodeExists(ProjectIdentifier $projectId, ServerIdentifier $serverId): bool;

    public function isServerInSwarmCluster(
        ServerIdentifier $serverId,
        ProjectIdentifier|null $excludeProjectId = null,
    ): bool;
}
