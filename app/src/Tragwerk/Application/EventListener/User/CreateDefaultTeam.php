<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use Chypriote\UniqueNames\Generator;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

final readonly class CreateDefaultTeam
{
    public function __construct(
        private TeamRepository $teamRepository,
    ) {
    }

    public function __invoke(Event\UserRegistered $event): void
    {
        $nameGenerator = new Generator();
        $nameGenerator
            ->setDictionaries([
                'adjectives',
                'colos',
                'animals',
            ])
            ->setSeparator(' ');

        $now  = TimestampImmutable::now();
        $team = new Team(
            TeamIdentifier::create(),
            $nameGenerator->generate(),
            $event->user->id,
            $now,
            $event->user->id,
            $now,
            $event->user->id,
        );

        $this->teamRepository->create($team);
        $this->teamRepository->assignUsers($team->id, [$event->user->id]);
    }
}
