<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware;

use Mezzio\Authentication\UserInterface;
use Mezzio\Router\RouteResult;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Infrastructure\Git\BareRepository;

use function array_key_exists;
use function assert;
use function is_string;

final readonly class EnvironmentMiddleware implements MiddlewareInterface
{
    public const string SESSION_KEY = 'active_environment_id';

    public function __construct(
        private BareRepository $bareRepository,
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        if (! $user instanceof UserInterface) {
            return $handler->handle($request);
        }

        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        assert($session instanceof SessionInterface);

        $project = $request->getAttribute('active_project');
        assert($project instanceof Project || $project === null);

        $route = $request->getAttribute(RouteResult::class);
        assert($route instanceof RouteResult);

        if ($project === null) {
            return $handler->handle($request);
        }

        // A missing or unreadable git repository must not break the request.
        try {
            $environments = $this->bareRepository->getBranches($project->id->toString());
        } catch (Throwable) {
            $environments = [];
        }

        if ($environments === []) {
            return $handler->handle(
                $request
                    ->withAttribute('project_environments', [])
                    ->withAttribute('active_environment', null),
            );
        }

        $environmentMap = [];
        foreach ($environments as $environment) {
            $environmentMap[$environment] = $environment;
        }

        if ($route->getMatchedRouteName() === 'environment.show') {
            $session->set(self::SESSION_KEY, $request->getQueryParams()['id'] ?? null);
        }

        $sessionEnvironmentId = $session->get(self::SESSION_KEY);

        if (is_string($sessionEnvironmentId) && array_key_exists($sessionEnvironmentId, $environmentMap)) {
            $activeEnvironment = $environmentMap[$sessionEnvironmentId];
        }

        return $handler->handle(
            $request
                ->withAttribute('project_environments', $environments)
                ->withAttribute('active_environment', $activeEnvironment ?? null),
        );
    }
}
