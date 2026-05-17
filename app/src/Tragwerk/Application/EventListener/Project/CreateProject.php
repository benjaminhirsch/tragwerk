<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Project;

use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Domain\Entity\ProjectInvitation;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\ProjectInvitationRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\ProjectInvitationIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function trim;

final readonly class CreateProject
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository,
        private ProjectInvitationRepository $projectInvitationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(Event\ProjectCreated $event): void
    {
        $project = $event->projectCreation->createProject($event->createdBy);
        $this->projectRepository->create($project);
        $this->projectRepository->assignUsers($project->id, [$event->createdBy]);

        foreach ($event->projectCreation->emailsToInvite as $rawEmail) {
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
                $event->createdBy,
            );

            $this->projectInvitationRepository->create($invitation);
            $this->eventDispatcher->dispatch(new Event\ProjectInvitationCreated($invitation));
        }
    }
}
