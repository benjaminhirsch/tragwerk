<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Repository;

use Tragwerk\Domain\Entity\ProjectWebhook;
use Tragwerk\Domain\Enum\GitForge;
use Tragwerk\Domain\Exception\Repository\EntityCreationFailed;
use Tragwerk\Domain\Exception\Repository\EntityDeletionFailed;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\WebhookIntegrationIdentifier;

interface ProjectWebhookRepository
{
    /** @throws EntityNotFound */
    public function getById(WebhookIntegrationIdentifier $id): ProjectWebhook;

    /** @return list<ProjectWebhook> */
    public function findByProject(ProjectIdentifier $projectId): array;

    public function findByProjectAndForge(ProjectIdentifier $projectId, GitForge $forge): ProjectWebhook|null;

    /** @throws EntityCreationFailed */
    public function create(ProjectWebhook $webhook): void;

    /** @throws EntityDeletionFailed */
    public function delete(WebhookIntegrationIdentifier $id): void;
}
