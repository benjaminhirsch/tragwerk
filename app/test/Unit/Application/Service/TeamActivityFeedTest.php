<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Service;

use Generator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tragwerk\Application\Service\TeamActivityFeed;
use Tragwerk\Domain\Entity\DeployJob;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\SetupJob;
use Tragwerk\Domain\Entity\TeamInvitation;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\DeployJobStatus;
use Tragwerk\Domain\Enum\SetupJobStatus;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\SetupJobRepository;
use Tragwerk\Domain\Repository\TeamInvitationRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\DeployJobIdentifier;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\SetupJobIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TeamInvitationIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function str_pad;

use const STR_PAD_LEFT;

#[AllowMockObjectsWithoutExpectations]
final class TeamActivityFeedTest extends TestCase
{
    private DeployJobRepository&MockObject $deployJobs;
    private SetupJobRepository&MockObject $setupJobs;
    private TeamInvitationRepository&MockObject $invitations;
    private UserRepository&MockObject $users;
    private TeamActivityFeed $feed;

    protected function setUp(): void
    {
        $this->deployJobs  = $this->createMock(DeployJobRepository::class);
        $this->setupJobs   = $this->createMock(SetupJobRepository::class);
        $this->invitations = $this->createMock(TeamInvitationRepository::class);
        $this->users       = $this->createMock(UserRepository::class);

        $this->feed = new TeamActivityFeed(
            $this->deployJobs,
            $this->setupJobs,
            $this->invitations,
            $this->users,
        );
    }

    #[Test]
    public function mergesAllSourcesSortedNewestFirst(): void
    {
        $teamId  = TeamIdentifier::create();
        $project = $this->project('storefront');
        $server  = $this->server('web-01');
        $inviter = UserIdentifier::create();

        $this->deployJobs->method('getRecentByProjects')->willReturn([
            $this->deployJob($project->id, 'main', DeployJobStatus::Completed, '2026-01-01T12:00:00+00:00'),
        ]);
        $this->setupJobs->method('getRecentByServers')->willReturn([
            $this->setupJob($server->id, SetupJobStatus::Completed, '2026-01-01T13:00:00+00:00'),
        ]);
        $this->invitations->method('getRecentByTeam')->willReturn([
            $this->invitation($teamId, 'new@example.com', $inviter, '2026-01-01T11:00:00+00:00'),
        ]);
        $this->users->method('getAll')->willReturnCallback(
            fn (): Generator => yield $this->user($inviter, 'Ada', 'Lovelace'),
        );

        $entries = $this->feed->build($teamId, [$project], [$server]);

        self::assertCount(3, $entries);
        // newest first: setup (13:00), deploy (12:00), invitation (11:00)
        self::assertSame('web-01', $entries[0]->subject);
        self::assertSame('storefront', $entries[1]->subject);
        self::assertStringContainsString('deployed to main', $entries[1]->detail);
        self::assertSame('Ada Lovelace', $entries[2]->subject);
        self::assertStringContainsString('invited new@example.com', $entries[2]->detail);
    }

    #[Test]
    public function respectsLimit(): void
    {
        $teamId  = TeamIdentifier::create();
        $project = $this->project('p');

        $jobs = [];
        for ($i = 0; $i < 12; $i++) {
            $at     = '2026-01-01T12:00:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . '+00:00';
            $jobs[] = $this->deployJob($project->id, 'main', DeployJobStatus::Completed, $at);
        }

        $this->deployJobs->method('getRecentByProjects')->willReturn($jobs);
        $this->setupJobs->method('getRecentByServers')->willReturn([]);
        $this->invitations->method('getRecentByTeam')->willReturn([]);

        $entries = $this->feed->build($teamId, [$project], [], 5);

        self::assertCount(5, $entries);
    }

    private function project(string $name): Project
    {
        $now = TimestampImmutable::now();
        $uid = UserIdentifier::create();

        return new Project(
            ProjectIdentifier::create(),
            $name,
            ServerIdentifier::create(),
            TeamIdentifier::create(),
            $now,
            $uid,
            $now,
            $uid,
            RegistryIdentifier::create(),
        );
    }

    private function server(string $name): Server
    {
        $now = TimestampImmutable::now();
        $uid = UserIdentifier::create();

        return new Server(
            ServerIdentifier::create(),
            $name,
            '203.0.113.1',
            null,
            TeamIdentifier::create(),
            $now,
            $uid,
            $now,
            $uid,
        );
    }

    private function deployJob(
        ProjectIdentifier $projectId,
        string $branch,
        DeployJobStatus $status,
        string $at,
    ): DeployJob {
        $ts = TimestampImmutable::fromString($at);

        return new DeployJob(
            DeployJobIdentifier::create(),
            $projectId,
            $branch,
            'abcdef1234567890',
            $status,
            '',
            $ts,
            $ts,
        );
    }

    private function setupJob(ServerIdentifier $serverId, SetupJobStatus $status, string $at): SetupJob
    {
        $ts = TimestampImmutable::fromString($at);

        return new SetupJob(
            SetupJobIdentifier::create(),
            $serverId,
            $status,
            '',
            $ts,
            $ts,
        );
    }

    private function invitation(
        TeamIdentifier $teamId,
        string $email,
        UserIdentifier $invitedBy,
        string $at,
    ): TeamInvitation {
        return new TeamInvitation(
            TeamInvitationIdentifier::create(),
            $teamId,
            $email,
            'token',
            TimestampImmutable::fromString($at),
            $invitedBy,
        );
    }

    private function user(UserIdentifier $id, string $first, string $last): User
    {
        $now = TimestampImmutable::now();

        return new User(
            $id,
            'u@example.com',
            $first,
            $last,
            PasswordHash::create('supersecret'),
            $now,
            $now,
        );
    }
}
