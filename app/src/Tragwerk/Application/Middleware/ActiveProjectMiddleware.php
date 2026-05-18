<?php

declare(strict_types=1);

namespace Tragwerk\Application\Middleware;

use Mezzio\Authentication\UserInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_key_exists;
use function array_key_first;
use function assert;
use function is_string;
use function iterator_to_array;

final readonly class ActiveProjectMiddleware implements MiddlewareInterface
{
    public const string SESSION_KEY = 'active_project_id';

    public function __construct(
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository,
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

        $userId   = UserIdentifier::fromString($user->getIdentity());
        $projects = iterator_to_array($this->projectRepository->getByUserId($userId), false);

        if ($projects === []) {
            return $handler->handle(
                $request
                    ->withAttribute('user_projects', [])
                    ->withAttribute('active_project', null),
            );
        }

        $projectMap = [];
        foreach ($projects as $project) {
            assert($project instanceof Project);
            $projectMap[$project->id->toString()] = $project;
        }

        $sessionProjectId = $session->get(self::SESSION_KEY);
        if (is_string($sessionProjectId) && array_key_exists($sessionProjectId, $projectMap)) {
            $activeProject = $projectMap[$sessionProjectId];
        } else {
            $activeProject = $this->resolveFromLastActive($userId, $projectMap);
            $session->set(self::SESSION_KEY, $activeProject->id->toString());
        }

        return $handler->handle(
            $request
                ->withAttribute('user_projects', $projects)
                ->withAttribute('active_project', $activeProject),
        );
    }

    /** @param array<string, Project> $projectMap */
    private function resolveFromLastActive(UserIdentifier $userId, array $projectMap): Project
    {
        $lastActiveId = $this->userRepository->getLastActiveProjectId($userId);

        if ($lastActiveId instanceof ProjectIdentifier) {
            $idString = $lastActiveId->toString();
            if (array_key_exists($idString, $projectMap)) {
                return $projectMap[$idString];
            }
        }

        $firstKey = array_key_first($projectMap);
        assert(is_string($firstKey));

        return $projectMap[$firstKey];
    }
}
