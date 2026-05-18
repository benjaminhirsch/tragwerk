<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\ProjectInvitation;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ProjectInvitationRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\ProjectInvitationIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function is_string;
use function trim;

final readonly class UpdateProject
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository,
        private ProjectInvitationRepository $projectInvitationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(Event\ProjectUpdated $event): void
    {
        $project = $this->projectRepository->getById($event->projectId);
        assert($project instanceof Project);

        $now                = TimestampImmutable::now();
        $project->name      = $event->projectUpdate->name;
        $project->updatedAt = $now;
        $project->updatedBy = $event->updatedBy;

        $this->projectRepository->update($project);

        foreach ($event->projectUpdate->usersToRemove as $rawId) {
            if (! is_string($rawId) || ! UserIdentifier::isValid($rawId)) {
                continue;
            }

            $removeId = UserIdentifier::fromString($rawId);
            if ($removeId->toString() === $project->ownerId->toString()) {
                continue;
            }

            $this->projectRepository->removeUser($project->id, $removeId);
        }

        foreach ($event->projectUpdate->emailsToInvite as $rawEmail) {
            $email = trim($rawEmail);
            if ($email === '') {
                continue;
            }

            $existingUser = $this->userRepository->searchByEmail($email)->current();
            if ($existingUser instanceof User) {
                $this->projectRepository->assignUsers($project->id, [$existingUser->id]);
                continue;
            }

            $invitation = new ProjectInvitation(
                ProjectInvitationIdentifier::create(),
                $project->id,
                $email,
                ProjectInvitationIdentifier::create()->toString(),
                TimestampImmutable::now(),
                $event->updatedBy,
            );

            $this->projectInvitationRepository->create($invitation);
            $this->eventDispatcher->dispatch(new Event\ProjectInvitationCreated($invitation));
        }
    }
}
