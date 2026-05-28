<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use CuyZ\Valinor\Mapper\TreeMapper;
use DOMDocument;
use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Config\XmlToArrayConverter;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Model\ProjectConfig;
use Tragwerk\Domain\Repository\BuildLogRepository;
use Tragwerk\Domain\Repository\EnvironmentRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Infrastructure\Git\BareRepository;
use Tragwerk\Infrastructure\Git\Commit;

use function assert;
use function in_array;
use function is_string;
use function iterator_to_array;

final readonly class EnvironmentHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private BareRepository $bareRepository,
        private BuildLogRepository $buildLogRepository,
        private EnvironmentRepository $environmentRepository,
        private XmlToArrayConverter $xmlConverter,
        private TreeMapper $treeMapper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        if (! $project instanceof Project) {
            return new EmptyResponse(404);
        }

        $params = $request->getQueryParams();
        $branch = is_string($params['branch'] ?? null) ? $params['branch'] : null;

        if ($branch === null || $branch === '') {
            return new EmptyResponse(400);
        }

        try {
            $commits = $this->bareRepository->getCommits($project->id->toString(), $branch);
        } catch (Throwable) {
            $commits = [];
        }

        $buildLogs = iterator_to_array(
            $this->buildLogRepository->getLatestByProjectAndBranch($project->id, $branch),
            false,
        );

        $isProtected   = in_array($branch, ['main', 'master'], true);
        $isActive      = $isProtected || $this->environmentRepository->isActive($project->id, $branch);
        $projectConfig = $this->loadProjectConfig($project->id->toString(), $commits);

        return $this->renderer->render($request, 'page::project/tab/environment', [
            'project'       => $project,
            'branch'        => $branch,
            'commits'       => $commits,
            'buildLogs'     => $buildLogs,
            'isActive'      => $isActive,
            'isProtected'   => $isProtected,
            'projectConfig' => $projectConfig,
        ]);
    }

    /** @param Commit[] $commits */
    private function loadProjectConfig(string $projectId, array $commits): ProjectConfig|null
    {
        $latestCommit = $commits[0] ?? null;
        if ($latestCommit === null) {
            return null;
        }

        $content = $this->bareRepository->getFileContent($projectId, $latestCommit->hash, '.tragwerk/config.xml');
        if ($content === null || $content === '') {
            return null;
        }

        try {
            $dom = new DOMDocument();
            if (! $dom->loadXML($content)) {
                return null;
            }

            $source = $this->xmlConverter->convert($dom);
            unset($source['xsi:noNamespaceSchemaLocation']);

            return $this->treeMapper->map(ProjectConfig::class, $source);
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
            assert($project instanceof Project);

            if ($project->teamId->toString() !== $activeTeam->id->toString()) {
                return null;
            }

            return $project;
        } catch (Throwable) {
            return null;
        }
    }
}
