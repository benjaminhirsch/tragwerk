<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project\Domain;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Domain;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\DomainDeleted;
use Tragwerk\Domain\Event\DomainSetPrimary;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\DomainIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function is_string;

final readonly class DeleteDomainHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private DomainRepository $domainRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);
        if (! $project instanceof Project) {
            return new EmptyResponse(404);
        }

        $domain = $this->resolveDomain($request, $project->id);
        if (! $domain instanceof Domain) {
            return new EmptyResponse(404);
        }

        $wasPrimary = $domain->isPrimary;
        $branch     = $domain->branch;
        $this->eventDispatcher->dispatch(new DomainDeleted($domain->id, $project->id));

        if ($wasPrimary) {
            $remaining = $this->domainRepository->findByEnvironment($project->id, $branch);
            if ($remaining !== []) {
                $this->eventDispatcher->dispatch(new DomainSetPrimary($remaining[0]->id, $project->id, $branch));
            }
        }

        return $this->renderer->render($request, 'partial::project/domain-list', [
            'project' => $project,
            'branch'  => $branch,
            'domains' => $this->domainRepository->findByEnvironment($project->id, $branch),
            'error'   => null,
        ]);
    }

    private function resolveDomain(ServerRequestInterface $request, ProjectIdentifier $projectId): Domain|null
    {
        $domainId = $request->getAttribute('domainId');
        if (! is_string($domainId) || ! DomainIdentifier::isValid($domainId)) {
            return null;
        }

        try {
            $domain = $this->domainRepository->getById(DomainIdentifier::fromString($domainId));

            return $domain->projectId->toString() === $projectId->toString() ? $domain : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveProject(ServerRequestInterface $request): Project|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! ProjectIdentifier::isValid($routeId)) {
            return null;
        }

        $activeTeam = $request->getAttribute('active_team');
        if (! $activeTeam instanceof Team) {
            return null;
        }

        try {
            $project = $this->projectRepository->getById(ProjectIdentifier::fromString($routeId));
            if (! $project instanceof Project) {
                return null;
            }

            return $project->teamId->toString() === $activeTeam->id->toString() ? $project : null;
        } catch (Throwable) {
            return null;
        }
    }
}
