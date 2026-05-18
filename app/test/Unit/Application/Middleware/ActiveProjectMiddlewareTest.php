<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Middleware;

use Generator;
use Mezzio\Authentication\UserInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Middleware\ActiveProjectMiddleware;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_values;

#[AllowMockObjectsWithoutExpectations]
final class ActiveProjectMiddlewareTest extends TestCase
{
    private ProjectRepository&MockObject $projectRepository;
    private UserRepository&MockObject $userRepository;
    private ActiveProjectMiddleware $middleware;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->userRepository    = $this->createMock(UserRepository::class);

        $this->middleware = new ActiveProjectMiddleware(
            $this->projectRepository,
            $this->userRepository,
        );
    }

    #[Test]
    public function unauthenticatedRequestPassesThroughWithoutProjectAttributes(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')
            ->with(UserInterface::class)
            ->willReturn(null);

        $handler  = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $this->projectRepository->expects(self::never())->method('getByUserId');

        $result = $this->middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function userWithNoProjectsReceivesNullActiveProject(): void
    {
        $userId  = UserIdentifier::create();
        $request = $this->buildAuthenticatedRequest($userId, null);

        $this->projectRepository->method('getByUserId')->willReturn($this->generatorFrom([]));

        $capturedRequest = null;
        $handler         = $this->handlerCapturing($capturedRequest);

        $this->middleware->process($request, $handler);

        self::assertNotNull($capturedRequest);

        self::assertNull($capturedRequest->getAttribute('active_project'));
        self::assertSame([], $capturedRequest->getAttribute('user_projects'));
    }

    #[Test]
    public function sessionProjectIdIsUsedWhenValidAndInProjectMap(): void
    {
        $userId  = UserIdentifier::create();
        $project = $this->makeProject($userId);
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')
            ->with(ActiveProjectMiddleware::SESSION_KEY)
            ->willReturn($project->id->toString());

        $request = $this->buildAuthenticatedRequest($userId, $session);

        $this->projectRepository->method('getByUserId')->willReturn($this->generatorFrom([$project]));
        $this->userRepository->expects(self::never())->method('getLastActiveProjectId');

        $capturedRequest = null;
        $handler         = $this->handlerCapturing($capturedRequest);

        $this->middleware->process($request, $handler);

        self::assertNotNull($capturedRequest);

        self::assertSame($project, $capturedRequest->getAttribute('active_project'));
    }

    #[Test]
    public function sessionProjectIdNotInMapFallsBackToLastActiveFromDatabase(): void
    {
        $userId   = UserIdentifier::create();
        $projectA = $this->makeProject($userId);
        $projectB = $this->makeProject($userId);
        $session  = $this->createMock(SessionInterface::class);

        $session->method('get')->willReturn(ProjectIdentifier::create()->toString());
        $session->expects(self::once())->method('set');

        $request = $this->buildAuthenticatedRequest($userId, $session);

        $this->projectRepository->method('getByUserId')
            ->willReturn($this->generatorFrom([$projectA, $projectB]));

        $this->userRepository->expects(self::once())
            ->method('getLastActiveProjectId')
            ->with($userId)
            ->willReturn($projectB->id);

        $capturedRequest = null;
        $handler         = $this->handlerCapturing($capturedRequest);

        $this->middleware->process($request, $handler);

        self::assertNotNull($capturedRequest);

        self::assertSame($projectB, $capturedRequest->getAttribute('active_project'));
    }

    #[Test]
    public function noSessionAndNoLastActiveFallsBackToFirstProject(): void
    {
        $userId   = UserIdentifier::create();
        $projectA = $this->makeProject($userId);
        $projectB = $this->makeProject($userId);
        $session  = $this->createMock(SessionInterface::class);

        $session->method('get')->willReturn(null);
        $session->expects(self::once())->method('set');

        $request = $this->buildAuthenticatedRequest($userId, $session);

        $this->projectRepository->method('getByUserId')
            ->willReturn($this->generatorFrom([$projectA, $projectB]));

        $this->userRepository->method('getLastActiveProjectId')->willReturn(null);

        $capturedRequest = null;
        $handler         = $this->handlerCapturing($capturedRequest);

        $this->middleware->process($request, $handler);

        self::assertNotNull($capturedRequest);

        self::assertSame($projectA, $capturedRequest->getAttribute('active_project'));
    }

    #[Test]
    public function allUserProjectsArePassedToRequestAttribute(): void
    {
        $userId   = UserIdentifier::create();
        $projectA = $this->makeProject($userId);
        $projectB = $this->makeProject($userId);
        $session  = $this->createMock(SessionInterface::class);

        $session->method('get')->willReturn($projectA->id->toString());

        $request = $this->buildAuthenticatedRequest($userId, $session);

        $this->projectRepository->method('getByUserId')
            ->willReturn($this->generatorFrom([$projectA, $projectB]));

        $capturedRequest = null;
        $handler         = $this->handlerCapturing($capturedRequest);

        $this->middleware->process($request, $handler);

        self::assertNotNull($capturedRequest);

        /** @var Project[] $projects */
        $projects = $capturedRequest->getAttribute('user_projects');
        self::assertCount(2, $projects);
        self::assertSame($projectA, $projects[0]);
        self::assertSame($projectB, $projects[1]);
    }

    private function buildAuthenticatedRequest(
        UserIdentifier $userId,
        SessionInterface|null $session,
    ): ServerRequestInterface {
        $user = $this->createMock(UserInterface::class);
        $user->method('getIdentity')->willReturn($userId->toString());

        $session ??= $this->createMock(SessionInterface::class);

        return (new Psr17Factory())
            ->createServerRequest('GET', '/')
            ->withAttribute(UserInterface::class, $user)
            ->withAttribute(SessionMiddleware::SESSION_ATTRIBUTE, $session);
    }

    /** @param Project[] $projects */
    private function generatorFrom(array $projects): Generator
    {
        yield from array_values($projects);
    }

    private function makeProject(UserIdentifier $ownerId): Project
    {
        $now = TimestampImmutable::now();

        return new Project(
            ProjectIdentifier::create(),
            'Test Project',
            $ownerId,
            $now,
            $ownerId,
            $now,
            $ownerId,
        );
    }

    private function handlerCapturing(ServerRequestInterface|null &$capturedRequest): RequestHandlerInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            static function (ServerRequestInterface $req) use (&$capturedRequest, $response): ResponseInterface {
                $capturedRequest = $req;

                return $response;
            },
        );

        return $handler;
    }
}
