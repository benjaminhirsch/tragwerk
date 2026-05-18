<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Project;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;

use function assert;

final class SwitchHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'switch-test@example.com';
    private const string PASSWORD = 'secure-password-123';

    private User $user;
    private string $sessionCookie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user          = $this->seedUser();
        $this->sessionCookie = $this->loginAndGetCookie();
    }

    #[Test]
    public function switchRedirectsToHomeWhenNoRefererProvided(): void
    {
        $project  = $this->seedProject('Project A');
        $response = $this->dispatch(
            'POST',
            $this->url('project.switch'),
            ['projectId' => $project->id->toString()],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('home'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function switchWithRefererRedirectsBackToReferer(): void
    {
        $project  = $this->seedProject('Project A');
        $response = $this->dispatch(
            'POST',
            $this->url('project.switch'),
            ['projectId' => $project->id->toString()],
            $this->sessionCookie,
            ['Referer' => '/some/page'],
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/some/page', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function switchWithValidProjectPersistsLastActiveProjectToDatabase(): void
    {
        $projectA = $this->seedProject('Project A');
        $projectB = $this->seedProject('Project B');

        $this->dispatch(
            'POST',
            $this->url('project.switch'),
            ['projectId' => $projectB->id->toString()],
            $this->sessionCookie,
        );

        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);

        $lastActive = $userRepository->getLastActiveProjectId($this->user->id);

        self::assertNotNull($lastActive);
        self::assertSame($projectB->id->toString(), $lastActive->toString());

        // suppress "unused variable" — $projectA is needed to have two projects in the pool
        unset($projectA);
    }

    #[Test]
    public function switchToProjectNotBelongingToUserDoesNotPersistToDatabase(): void
    {
        $otherUser    = $this->seedOtherUser();
        $otherProject = $this->seedProjectForUser('Foreign Project', $otherUser);

        $this->dispatch(
            'POST',
            $this->url('project.switch'),
            ['projectId' => $otherProject->id->toString()],
            $this->sessionCookie,
        );

        $userRepository = $this->container->get(UserRepository::class);
        assert($userRepository instanceof UserRepository);

        $lastActive = $userRepository->getLastActiveProjectId($this->user->id);

        self::assertNull($lastActive);
    }

    #[Test]
    public function switchWithInvalidUuidDoesNotCrash(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.switch'),
            ['projectId' => 'not-a-uuid'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Switch',
            'Tester',
            PasswordHash::create(self::PASSWORD),
            $now,
            $now,
        );

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $repository->create($user);

        return $user;
    }

    private function seedOtherUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            'other-' . self::EMAIL,
            'Other',
            'User',
            PasswordHash::create(self::PASSWORD),
            $now,
            $now,
        );

        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);
        $repository->create($user);

        return $user;
    }

    private function seedProject(string $name): Project
    {
        return $this->seedProjectForUser($name, $this->user);
    }

    private function seedProjectForUser(string $name, User $owner): Project
    {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            $name,
            $owner->id,
            $now,
            $owner->id,
            $now,
            $owner->id,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $repository->create($project);
        $repository->assignUsers($project->id, [$owner->id]);

        return $project;
    }

    private function loginAndGetCookie(): string
    {
        $response = $this->dispatch('POST', $this->url('login'), [
            'email'    => self::EMAIL,
            'password' => self::PASSWORD,
        ]);

        return $this->getSessionCookie($response);
    }
}
