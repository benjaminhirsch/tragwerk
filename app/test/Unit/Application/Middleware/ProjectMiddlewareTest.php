<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Middleware;

use Generator;
use Mezzio\Authentication\UserInterface;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Middleware\ProjectMiddleware;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\RegistryIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

#[AllowMockObjectsWithoutExpectations]
final class ProjectMiddlewareTest extends TestCase
{
    private ProjectRepository&MockObject $projectRepository;
    private ProjectMiddleware $middleware;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->middleware        = new ProjectMiddleware($this->projectRepository);
    }

    #[Test]
    public function unauthenticatedRequestPassesThrough(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->with(UserInterface::class)->willReturn(null);

        $this->projectRepository->expects(self::never())->method('getAll');

        $response = $this->createMock(ResponseInterface::class);
        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        self::assertSame($response, $this->middleware->process($request, $handler));
    }

    #[Test]
    public function authenticatedUserWithoutActiveTeamPassesThrough(): void
    {
        // Regression: must not assert/500 when no active team is set.
        $request = $this->buildRequest(null);

        $this->projectRepository->expects(self::never())->method('getAll');

        $captured = null;
        $this->middleware->process($request, $this->handlerCapturing($captured));

        self::assertNotNull($captured);
        self::assertNull($captured->getAttribute('active_project'));
    }

    #[Test]
    public function teamWithoutProjectsYieldsNullActiveProject(): void
    {
        $request = $this->buildRequest($this->makeTeam());
        $this->projectRepository->method('getAll')->willReturn($this->generatorFrom([]));

        $captured = null;
        $this->middleware->process($request, $this->handlerCapturing($captured));

        self::assertNotNull($captured);
        self::assertNull($captured->getAttribute('active_project'));
        self::assertSame([], $captured->getAttribute('team_projects'));
    }

    #[Test]
    public function singleProjectIsAutoSelected(): void
    {
        $team    = $this->makeTeam();
        $project = $this->makeProject($team->id);
        $request = $this->buildRequest($team);

        $this->projectRepository->method('getAll')->willReturn($this->generatorFrom([$project]));

        $captured = null;
        $this->middleware->process($request, $this->handlerCapturing($captured));

        self::assertNotNull($captured);
        self::assertSame($project, $captured->getAttribute('active_project'));
    }

    #[Test]
    public function sessionProjectIdSelectsActiveProject(): void
    {
        $team     = $this->makeTeam();
        $projectA = $this->makeProject($team->id);
        $projectB = $this->makeProject($team->id);

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->with(ProjectMiddleware::SESSION_KEY)->willReturn($projectB->id->toString());

        $request = $this->buildRequest($team, $session);
        $this->projectRepository->method('getAll')->willReturn($this->generatorFrom([$projectA, $projectB]));

        $captured = null;
        $this->middleware->process($request, $this->handlerCapturing($captured));

        self::assertNotNull($captured);
        self::assertSame($projectB, $captured->getAttribute('active_project'));
    }

    #[Test]
    public function projectShowRouteWritesActiveProjectToSession(): void
    {
        $team     = $this->makeTeam();
        $projectA = $this->makeProject($team->id);
        $projectB = $this->makeProject($team->id);

        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn(null);
        $session->expects(self::once())
            ->method('set')
            ->with(ProjectMiddleware::SESSION_KEY, $projectB->id->toString());

        $request = $this->buildRequest($team, $session, 'project.show', $projectB->id->toString());
        $this->projectRepository->method('getAll')->willReturn($this->generatorFrom([$projectA, $projectB]));

        $this->middleware->process($request, $this->handlerCapturing($captured));
    }

    private function buildRequest(
        Team|null $team,
        SessionInterface|null $session = null,
        string $routeName = 'project',
        string|null $routeId = null,
    ): ServerRequestInterface {
        $user = $this->createMock(UserInterface::class);
        $user->method('getIdentity')->willReturn(UserIdentifier::create()->toString());

        $session ??= $this->createMock(SessionInterface::class);

        if ($routeName === 'project.show') {
            $mw    = $this->createMock(MiddlewareInterface::class);
            $route = RouteResult::fromRoute(new Route('/projects/{id}', $mw, ['GET'], 'project.show'));
        } else {
            $route = RouteResult::fromRouteFailure(null);
        }

        $request = (new Psr17Factory())
            ->createServerRequest('GET', '/')
            ->withAttribute(UserInterface::class, $user)
            ->withAttribute(SessionMiddleware::SESSION_ATTRIBUTE, $session)
            ->withAttribute(RouteResult::class, $route)
            ->withAttribute('active_team', $team);

        if ($routeId !== null) {
            $request = $request->withAttribute('id', $routeId);
        }

        return $request;
    }

    /** @param Project[] $projects */
    private function generatorFrom(array $projects): Generator
    {
        yield from $projects;
    }

    private function makeTeam(): Team
    {
        $now = TimestampImmutable::now();
        $uid = UserIdentifier::create();

        return new Team(TeamIdentifier::create(), 'Acme', $uid, $now, $uid, $now, $uid);
    }

    private function makeProject(TeamIdentifier $teamId): Project
    {
        $now = TimestampImmutable::now();
        $uid = UserIdentifier::create();

        return new Project(
            ProjectIdentifier::create(),
            'Project',
            ServerIdentifier::create(),
            $teamId,
            $now,
            $uid,
            $now,
            $uid,
            RegistryIdentifier::create(),
        );
    }

    private function handlerCapturing(ServerRequestInterface|null &$captured): RequestHandlerInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $handler  = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(
            static function (ServerRequestInterface $req) use (&$captured, $response): ResponseInterface {
                $captured = $req;

                return $response;
            },
        );

        return $handler;
    }
}
