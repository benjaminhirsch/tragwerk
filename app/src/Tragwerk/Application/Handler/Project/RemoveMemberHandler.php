<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_filter;
use function array_merge;
use function array_unique;
use function array_values;
use function assert;
use function in_array;
use function is_array;
use function is_string;
use function iterator_to_array;

final readonly class RemoveMemberHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ResponseRenderer $renderer,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        $members       = [];
        $usersToRemove = [];
        $ownerId       = '';
        $projectId     = '';

        if ($project instanceof Project) {
            $projectId = $project->id->toString();
            $ownerId   = $project->ownerId->toString();
            $body      = $request->getParsedBody();

            $pendingRemovals = is_array($body) && is_array($body['usersToRemove'] ?? null)
                ? $body['usersToRemove']
                : [];

            $newRemoval = is_array($body) && is_string($body['userId'] ?? null) ? $body['userId'] : null;

            $allToRemove = array_unique(array_merge(
                array_filter($pendingRemovals, static fn (mixed $v) => is_string($v) && UserIdentifier::isValid($v)),
                $newRemoval !== null && UserIdentifier::isValid($newRemoval) && $newRemoval !== $ownerId
                    ? [$newRemoval]
                    : [],
            ));

            $allMembers = iterator_to_array($this->projectRepository->getUsersByProjectId($project->id), false);

            $members = array_values(array_filter(
                $allMembers,
                static fn (User $u) => ! in_array($u->id->toString(), $allToRemove, true),
            ));

            $usersToRemove = array_values($allToRemove);
        }

        return $this->renderer->render($request, 'partial::project/member-list', [
            'members'       => $members,
            'projectId'     => $projectId,
            'ownerId'       => $ownerId,
            'usersToRemove' => $usersToRemove,
        ]);
    }

    private function resolveProject(ServerRequestInterface $request): Project|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! ProjectIdentifier::isValid($routeId)) {
            return null;
        }

        $raw = $request->getAttribute('user_projects');
        if (! is_array($raw)) {
            return null;
        }

        foreach ($raw as $project) {
            assert($project instanceof Project);
            if ($project->id->toString() === $routeId) {
                return $project;
            }
        }

        return null;
    }
}
