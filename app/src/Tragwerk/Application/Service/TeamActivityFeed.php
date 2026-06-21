<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service;

use Tragwerk\Application\ReadModel\ActivityEntry;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\SetupJob;
use Tragwerk\Domain\Entity\TeamInvitation;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\SetupJobRepository;
use Tragwerk\Domain\Repository\TeamInvitationRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\TeamIdentifier;

use function array_map;
use function array_slice;
use function array_values;
use function assert;
use function ucfirst;
use function usort;

/**
 * Builds the team activity feed by merging recent deploy jobs, setup jobs and
 * invitations into a single, time-ordered list of display-ready entries.
 */
final readonly class TeamActivityFeed
{
    public function __construct(
        private DeployJobRepository $deployJobRepository,
        private SetupJobRepository $setupJobRepository,
        private TeamInvitationRepository $teamInvitationRepository,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @param list<Project> $projects
     * @param list<Server>  $servers
     *
     * @return list<ActivityEntry>
     */
    public function build(TeamIdentifier $teamId, array $projects, array $servers, int $limit = 8): array
    {
        $entries = [
            ...$this->deployEntries($projects, $limit),
            ...$this->setupEntries($servers, $limit),
            ...$this->invitationEntries($teamId, $limit),
        ];

        usort($entries, static function (ActivityEntry $a, ActivityEntry $b): int {
            return $b->occurredAt->format('U') <=> $a->occurredAt->format('U');
        });

        return array_slice($entries, 0, $limit);
    }

    /**
     * @param list<Project> $projects
     *
     * @return list<ActivityEntry>
     */
    private function deployEntries(array $projects, int $limit): array
    {
        if ($projects === []) {
            return [];
        }

        $names      = [];
        $projectIds = [];
        foreach ($projects as $project) {
            $id           = $project->id->toString();
            $names[$id]   = $project->name;
            $projectIds[] = $id;
        }

        $entries = [];
        foreach ($this->deployJobRepository->getRecentByProjects($projectIds, $limit) as $job) {
            assert($job instanceof DeployJob);

            $entries[] = new ActivityEntry(
                icon:         'bi bi-rocket-takeoff',
                iconColorVar: $job->status->value === 'failed' ? '--danger' : '--accent',
                subject:      $names[$job->projectId->toString()] ?? $job->projectId->toString(),
                detail:       'deployed to ' . $job->branch . ' · ' . ucfirst($job->status->value),
                occurredAt:   $job->createdAt,
            );
        }

        return $entries;
    }

    /**
     * @param list<Server> $servers
     *
     * @return list<ActivityEntry>
     */
    private function setupEntries(array $servers, int $limit): array
    {
        if ($servers === []) {
            return [];
        }

        $names     = [];
        $serverIds = [];
        foreach ($servers as $server) {
            $id          = $server->id->toString();
            $names[$id]  = $server->name;
            $serverIds[] = $id;
        }

        $entries = [];
        foreach ($this->setupJobRepository->getRecentByServers($serverIds, $limit) as $job) {
            assert($job instanceof SetupJob);

            $entries[] = new ActivityEntry(
                icon:         'bi bi-hdd-stack',
                iconColorVar: $job->status->value === 'failed' ? '--danger' : '--purple',
                subject:      $names[$job->serverId->toString()] ?? $job->serverId->toString(),
                detail:       'setup ' . ucfirst($job->status->value),
                occurredAt:   $job->createdAt,
            );
        }

        return $entries;
    }

    /** @return list<ActivityEntry> */
    private function invitationEntries(TeamIdentifier $teamId, int $limit): array
    {
        $invitations = $this->teamInvitationRepository->getRecentByTeam($teamId, $limit);
        if ($invitations === []) {
            return [];
        }

        $inviterIds = array_values(array_map(
            static fn (TeamInvitation $i) => $i->invitedBy,
            $invitations,
        ));

        $inviterNames = [];
        foreach ($this->userRepository->getAll(ids: $inviterIds) as $user) {
            assert($user instanceof User);
            $inviterNames[$user->id->toString()] = $user->fullName();
        }

        $entries = [];
        foreach ($invitations as $invitation) {
            $entries[] = new ActivityEntry(
                icon:         'bi bi-person-plus',
                iconColorVar: '--ok',
                subject:      $inviterNames[$invitation->invitedBy->toString()] ?? 'Someone',
                detail:       'invited ' . $invitation->email,
                occurredAt:   $invitation->invitedAt,
            );
        }

        return $entries;
    }
}
