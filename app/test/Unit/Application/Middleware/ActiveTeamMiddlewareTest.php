<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Middleware;

use Generator;
use Mezzio\Authentication\UserInterface;
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
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Middleware\TeamMiddleware;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_values;

#[AllowMockObjectsWithoutExpectations]
final class ActiveTeamMiddlewareTest extends TestCase
{
    private TeamRepository&MockObject $teamRepository;
    private UserRepository&MockObject $userRepository;
    private TeamMiddleware $middleware;

    protected function setUp(): void
    {
        $this->teamRepository = $this->createMock(TeamRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->middleware = new TeamMiddleware(
            $this->teamRepository,
            $this->userRepository,
        );
    }

    #[Test]
    public function unauthenticatedRequestPassesThroughWithoutTeamAttributes(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')
            ->with(UserInterface::class)
            ->willReturn(null);

        $handler  = $this->createMock(RequestHandlerInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $handler->expects(self::once())->method('handle')->with($request)->willReturn($response);

        $this->teamRepository->expects(self::never())->method('getByUserId');

        $result = $this->middleware->process($request, $handler);

        self::assertSame($response, $result);
    }

    #[Test]
    public function userWithNoTeamsReceivesNullActiveTeam(): void
    {
        $userId  = UserIdentifier::create();
        $request = $this->buildAuthenticatedRequest($userId, null);

        $this->teamRepository->method('getByUserId')->willReturn($this->generatorFrom([]));

        $capturedRequest = null;
        $handler         = $this->handlerCapturing($capturedRequest);

        $this->middleware->process($request, $handler);

        self::assertNotNull($capturedRequest);

        self::assertNull($capturedRequest->getAttribute('active_team'));
        self::assertSame([], $capturedRequest->getAttribute('user_teams'));
    }

    #[Test]
    public function sessionTeamIdIsUsedWhenValidAndInTeamMap(): void
    {
        $userId  = UserIdentifier::create();
        $team    = $this->makeTeam($userId);
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')
            ->with(TeamMiddleware::SESSION_KEY)
            ->willReturn($team->id->toString());

        $request = $this->buildAuthenticatedRequest($userId, $session);

        $this->teamRepository->method('getByUserId')->willReturn($this->generatorFrom([$team]));
        $this->userRepository->expects(self::never())->method('getLastActiveTeamId');

        $capturedRequest = null;
        $handler         = $this->handlerCapturing($capturedRequest);

        $this->middleware->process($request, $handler);

        self::assertNotNull($capturedRequest);

        self::assertSame($team, $capturedRequest->getAttribute('active_team'));
    }

    #[Test]
    public function sessionTeamIdNotInMapFallsBackToLastActiveFromDatabase(): void
    {
        $userId  = UserIdentifier::create();
        $teamA   = $this->makeTeam($userId);
        $teamB   = $this->makeTeam($userId);
        $session = $this->createMock(SessionInterface::class);

        $session->method('get')->willReturn(TeamIdentifier::create()->toString());
        $session->expects(self::once())->method('set');

        $request = $this->buildAuthenticatedRequest($userId, $session);

        $this->teamRepository->method('getByUserId')
            ->willReturn($this->generatorFrom([$teamA, $teamB]));

        $this->userRepository->expects(self::once())
            ->method('getLastActiveTeamId')
            ->with($userId)
            ->willReturn($teamB->id);

        $capturedRequest = null;
        $handler         = $this->handlerCapturing($capturedRequest);

        $this->middleware->process($request, $handler);

        self::assertNotNull($capturedRequest);

        self::assertSame($teamB, $capturedRequest->getAttribute('active_team'));
    }

    #[Test]
    public function noSessionAndNoLastActiveFallsBackToFirstTeam(): void
    {
        $userId  = UserIdentifier::create();
        $teamA   = $this->makeTeam($userId);
        $teamB   = $this->makeTeam($userId);
        $session = $this->createMock(SessionInterface::class);

        $session->method('get')->willReturn(null);
        $session->expects(self::once())->method('set');

        $request = $this->buildAuthenticatedRequest($userId, $session);

        $this->teamRepository->method('getByUserId')
            ->willReturn($this->generatorFrom([$teamA, $teamB]));

        $this->userRepository->method('getLastActiveTeamId')->willReturn(null);

        $capturedRequest = null;
        $handler         = $this->handlerCapturing($capturedRequest);

        $this->middleware->process($request, $handler);

        self::assertNotNull($capturedRequest);

        self::assertSame($teamA, $capturedRequest->getAttribute('active_team'));
    }

    #[Test]
    public function allUserTeamsArePassedToRequestAttribute(): void
    {
        $userId  = UserIdentifier::create();
        $teamA   = $this->makeTeam($userId);
        $teamB   = $this->makeTeam($userId);
        $session = $this->createMock(SessionInterface::class);

        $session->method('get')->willReturn($teamA->id->toString());

        $request = $this->buildAuthenticatedRequest($userId, $session);

        $this->teamRepository->method('getByUserId')
            ->willReturn($this->generatorFrom([$teamA, $teamB]));

        $capturedRequest = null;
        $handler         = $this->handlerCapturing($capturedRequest);

        $this->middleware->process($request, $handler);

        self::assertNotNull($capturedRequest);

        /** @var Team[] $teams */
        $teams = $capturedRequest->getAttribute('user_teams');
        self::assertCount(2, $teams);
        self::assertSame($teamA, $teams[0]);
        self::assertSame($teamB, $teams[1]);
    }

    private function buildAuthenticatedRequest(
        UserIdentifier $userId,
        SessionInterface|null $session,
    ): ServerRequestInterface {
        $user = $this->createMock(UserInterface::class);
        $user->method('getIdentity')->willReturn($userId->toString());

        $session ??= $this->createMock(SessionInterface::class);

        $route = RouteResult::fromRouteFailure(null);

        return (new Psr17Factory())
            ->createServerRequest('GET', '/')
            ->withAttribute(UserInterface::class, $user)
            ->withAttribute(SessionMiddleware::SESSION_ATTRIBUTE, $session)
            ->withAttribute(RouteResult::class, $route);
    }

    /** @param Team[] $teams */
    private function generatorFrom(array $teams): Generator
    {
        yield from array_values($teams);
    }

    private function makeTeam(UserIdentifier $ownerId): Team
    {
        $now = TimestampImmutable::now();

        return new Team(
            TeamIdentifier::create(),
            'Test Team',
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
