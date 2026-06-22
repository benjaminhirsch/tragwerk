<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Registry;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\DeployJobRepository;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\RegistryRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Git\BareRepository;

use function array_keys;
use function in_array;
use function is_string;
use function substr;

final readonly class ShowHandler implements RequestHandlerInterface
{
    private const array ROOT_BRANCHES = ['main', 'master'];

    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private ServerRepository $serverRepository,
        private RegistryRepository $registryRepository,
        private DeployJobRepository $deployJobRepository,
        private DomainRepository $domainRepository,
        private BareRepository $bareRepository,
        private UrlHelper $urlHelper,
        private string $gitSshHost,
        private string $gitSshRepoBase,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        if (! $project instanceof Project) {
            return new RedirectResponse($this->urlHelper->generate('project'));
        }

        try {
            $parents = $this->bareRepository->getBranchParents($project->id->toString());
        } catch (Throwable) {
            $parents = [];
        }

        $branches = array_keys($parents);
        $statuses = $this->deployJobRepository->getLatestStatusByProjectAndBranches($project->id, $branches);

        $environments = [];
        foreach ($parents as $branch => $parent) {
            $latest = $this->deployJobRepository->getLatestByProjectAndBranch($project->id, $branch);

            $environments[] = [
                'name'       => $branch,
                'isRoot'     => $parent === null,
                'hasParent'  => $parent !== null,
                'status'     => ($statuses[$branch] ?? null)?->value,
                'commit'     => $latest !== null ? substr($latest->commitSha, 0, 7) : null,
                'deployedAt' => $latest?->createdAt,
            ];
        }

        $productionBranch = $this->productionBranch($branches);
        $productionUrl    = $productionBranch !== null ? $this->primaryDomain($project, $productionBranch) : null;
        $productionStatus = $productionBranch !== null ? ($statuses[$productionBranch] ?? null)?->value : null;
        $cloneUrl         = 'git@' . $this->gitSshHost . ':' . $this->gitSshRepoBase . '/' . $project->id->toString();

        return $this->renderer->render($request, 'page::project/show', [
            'project'          => $project,
            'server'           => $this->server($project),
            'registry'         => $this->registry($project),
            'environments'     => $environments,
            'productionUrl'    => $productionUrl,
            'productionStatus' => $productionStatus,
            'cloneUrl'         => $cloneUrl,
            'activity'         => $this->deployJobRepository->getRecentByProjects([$project->id->toString()], 8),
        ]);
    }

    private function server(Project $project): Server|null
    {
        try {
            $server = $this->serverRepository->getById($project->serverId);

            return $server instanceof Server ? $server : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function registry(Project $project): Registry|null
    {
        try {
            $registry = $this->registryRepository->getById($project->registryId);

            return $registry instanceof Registry ? $registry : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param list<string> $branches */
    private function productionBranch(array $branches): string|null
    {
        foreach (self::ROOT_BRANCHES as $root) {
            if (in_array($root, $branches, true)) {
                return $root;
            }
        }

        return $branches[0] ?? null;
    }

    private function primaryDomain(Project $project, string $branch): string|null
    {
        foreach ($this->domainRepository->findByEnvironment($project->id, $branch) as $domain) {
            if ($domain->isPrimary) {
                return 'https://' . $domain->host;
            }
        }

        return null;
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
