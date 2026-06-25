<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Middleware;

use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authorization\AuthorizationInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Middleware\TeamAuthorizationMiddleware;
use Tragwerk\Domain\Enum\TeamPermission;

#[AllowMockObjectsWithoutExpectations]
final class TeamAuthorizationMiddlewareTest extends TestCase
{
    private AuthorizationInterface&MockObject $authorization;
    private UrlHelper&MockObject $urlHelper;
    private TeamAuthorizationMiddleware $middleware;

    protected function setUp(): void
    {
        $this->authorization = $this->createMock(AuthorizationInterface::class);
        $this->urlHelper     = $this->createMock(UrlHelper::class);
        $this->urlHelper->method('generate')->willReturn('/teams');

        $this->middleware = new TeamAuthorizationMiddleware($this->authorization, $this->urlHelper);
    }

    #[Test]
    public function passesThroughWhenNoRouteResult(): void
    {
        $request = (new Psr17Factory())->createServerRequest('GET', '/teams/1');
        $this->authorization->expects(self::never())->method('isGranted');

        $result = $this->middleware->process($request, $this->expectHandlerCalled($request));

        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function passesThroughWhenRouteHasNoPermissionOption(): void
    {
        $request = $this->requestForRoute('GET', '/teams/1', []);
        $this->authorization->expects(self::never())->method('isGranted');

        $result = $this->middleware->process($request, $this->expectHandlerCalled($request));

        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function callsHandlerWhenPermissionGranted(): void
    {
        $request = $this->requestForRoute('GET', '/teams/1', [
            TeamAuthorizationMiddleware::OPTION_REQUIRE_PERMISSION => TeamPermission::EditTeam,
        ]);
        $this->authorization->method('isGranted')->with('edit-team', $request)->willReturn(true);

        $result = $this->middleware->process($request, $this->expectHandlerCalled($request));

        self::assertSame(200, $result->getStatusCode());
    }

    #[Test]
    public function redirectsWhenPermissionDenied(): void
    {
        $request = $this->requestForRoute('POST', '/teams/1/edit', [
            TeamAuthorizationMiddleware::OPTION_REQUIRE_PERMISSION => TeamPermission::EditTeam,
        ]);
        $this->authorization->method('isGranted')->willReturn(false);

        $result = $this->middleware->process($request, $this->expectHandlerNeverCalled());

        self::assertInstanceOf(RedirectResponse::class, $result);
        self::assertSame(302, $result->getStatusCode());
        self::assertSame('/teams', $result->getHeaderLine('Location'));
    }

    #[Test]
    public function htmxDeniedReturnsHxRedirect(): void
    {
        $request = $this->requestForRoute('POST', '/teams/1/members/remove', [
            TeamAuthorizationMiddleware::OPTION_REQUIRE_PERMISSION => TeamPermission::ManageMembers,
        ])->withHeader('HX-Request', 'true');
        $this->authorization->method('isGranted')->willReturn(false);

        $result = $this->middleware->process($request, $this->expectHandlerNeverCalled());

        self::assertInstanceOf(EmptyResponse::class, $result);
        self::assertSame(200, $result->getStatusCode());
        self::assertSame('/teams', $result->getHeaderLine('HX-Redirect'));
    }

    #[Test]
    public function apiDeniedReturnsJson403(): void
    {
        $request = $this->requestForRoute('POST', '/api/teams/1/edit', [
            TeamAuthorizationMiddleware::OPTION_REQUIRE_PERMISSION => TeamPermission::EditTeam,
        ]);
        $this->authorization->method('isGranted')->willReturn(false);

        $result = $this->middleware->process($request, $this->expectHandlerNeverCalled());

        self::assertInstanceOf(JsonResponse::class, $result);
        self::assertSame(403, $result->getStatusCode());
    }

    /**
     * @param non-empty-string     $path
     * @param array<string, mixed> $options
     */
    private function requestForRoute(string $method, string $path, array $options): ServerRequestInterface
    {
        $route = new Route($path, $this->createMock(MiddlewareInterface::class), [$method]);
        $route->setOptions($options);

        return (new Psr17Factory())
            ->createServerRequest($method, $path)
            ->withAttribute(RouteResult::class, RouteResult::fromRoute($route));
    }

    private function expectHandlerCalled(ServerRequestInterface $expected): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::once())
            ->method('handle')
            ->with($expected)
            ->willReturn(new EmptyResponse(200));

        return $handler;
    }

    private function expectHandlerNeverCalled(): RequestHandlerInterface
    {
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        return $handler;
    }
}
