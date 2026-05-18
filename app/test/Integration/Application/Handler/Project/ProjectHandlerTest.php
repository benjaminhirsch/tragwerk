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

final class ProjectHandlerTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'project-test@example.com';
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
    public function listRendersProjectsPage(): void
    {
        $response = $this->dispatch('GET', $this->url('project'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function listRedirectsUnauthenticatedUserToLogin(): void
    {
        $response = $this->dispatch('GET', $this->url('project'));

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('login'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createGetRendersForm(): void
    {
        $response = $this->dispatch('GET', $this->url('project.create'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithValidDataRedirectsToProjectList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.create'),
            ['name' => 'My Test Project'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('project'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function createPostPersistsProjectInDatabase(): void
    {
        $this->dispatch(
            'POST',
            $this->url('project.create'),
            ['name' => 'My Test Project'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);

        $projects = [...$repository->getByUserId($this->user->id)];
        self::assertCount(1, $projects);
        self::assertInstanceOf(Project::class, $projects[0]);
        self::assertSame('My Test Project', $projects[0]->name);
    }

    #[Test]
    public function createPostWithEmptyNameReRendersForm(): void
    {
        $response = $this->dispatch('POST', $this->url('project.create'), ['name' => ''], $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editGetRendersFormWithProject(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'GET',
            $this->url('project.edit', ['id' => $project->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editPostWithValidDataRedirectsToProjectList(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.edit', ['id' => $project->id->toString()]),
            ['name' => 'Updated Project Name'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('project'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function editPostUpdatesProjectNameInDatabase(): void
    {
        $project = $this->seedProject();
        $this->dispatch(
            'POST',
            $this->url('project.edit', ['id' => $project->id->toString()]),
            ['name' => 'Updated Project Name'],
            $this->sessionCookie,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);

        $updated = $repository->getById($project->id);
        assert($updated instanceof Project);
        self::assertSame('Updated Project Name', $updated->name);
    }

    #[Test]
    public function editPostWithEmptyNameReRendersForm(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.edit', ['id' => $project->id->toString()]),
            ['name' => ''],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function editGetWithUnknownProjectIdRedirectsToProjectList(): void
    {
        $response = $this->dispatch(
            'GET',
            $this->url('project.edit', ['id' => ProjectIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('project'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostRedirectsToProjectList(): void
    {
        $project  = $this->seedProject();
        $response = $this->dispatch(
            'POST',
            $this->url('project.delete', ['id' => $project->id->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('project'), $response->getHeaderLine('Location'));
    }

    #[Test]
    public function deletePostRemovesProjectFromDatabase(): void
    {
        $project = $this->seedProject();
        $this->dispatch(
            'POST',
            $this->url('project.delete', ['id' => $project->id->toString()]),
            cookie: $this->sessionCookie,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);

        $remaining = [...$repository->getByUserId($this->user->id)];
        self::assertCount(0, $remaining);
    }

    #[Test]
    public function deletePostWithUnknownProjectIdRedirectsToProjectList(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.delete', ['id' => ProjectIdentifier::create()->toString()]),
            cookie: $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('project'), $response->getHeaderLine('Location'));
    }

    private function seedUser(): User
    {
        $now  = TimestampImmutable::now();
        $user = new User(
            UserIdentifier::create(),
            self::EMAIL,
            'Project',
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

    private function seedProject(): Project
    {
        $now     = TimestampImmutable::now();
        $project = new Project(
            ProjectIdentifier::create(),
            'Test Project',
            $this->user->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        );

        $repository = $this->container->get(ProjectRepository::class);
        assert($repository instanceof ProjectRepository);
        $repository->create($project);
        $repository->assignUsers($project->id, [$this->user->id]);

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
