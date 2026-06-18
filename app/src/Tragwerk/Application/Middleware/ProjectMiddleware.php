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
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\ProjectRepository;

use function array_key_exists;
use function assert;
use function is_string;
use function iterator_to_array;

final readonly class ProjectMiddleware implements MiddlewareInterface
{
    public const string SESSION_KEY = 'active_project_id';

    public function __construct(
        private ProjectRepository $projectRepository,
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

        $team = $request->getAttribute('active_team');
        assert($team instanceof Team);

        $route = $request->getAttribute(RouteResult::class);
        assert($route instanceof RouteResult);

        $projects = iterator_to_array($this->projectRepository->getAll($team->id), false);

        if ($projects === []) {
            return $handler->handle(
                $request
                    ->withAttribute('team_projects', [])
                    ->withAttribute('active_project', null),
            );
        }

        $projectMap = [];
        foreach ($projects as $project) {
            assert($project instanceof Project);
            $projectMap[$project->id->toString()] = $project;
        }

        if ($route->getMatchedRouteName() === 'project.show') {
            $session->set(self::SESSION_KEY, $request->getAttribute('id'));
        }

        if ($route->getMatchedRouteName() === 'team.show') {
            $session->unset(self::SESSION_KEY);
        }

        $sessionProjectId = $session->get(self::SESSION_KEY);

        if (is_string($sessionProjectId) && array_key_exists($sessionProjectId, $projectMap)) {
            $activeProject = $projectMap[$sessionProjectId];
        }

        return $handler->handle(
            $request
                ->withAttribute('team_projects', $projects)
                ->withAttribute('active_project', $activeProject ?? null),
        );
    }
}
