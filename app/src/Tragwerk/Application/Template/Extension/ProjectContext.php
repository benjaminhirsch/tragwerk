<?php

declare(strict_types=1);

namespace Tragwerk\Application\Template\Extension;

use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Domain\Entity\Project;

use function is_array;

final class ProjectContext implements MiddlewareInterface, ExtensionInterface
{
    private Project|null $activeProject = null;

    /** @var Project[] */
    private array $teamProjects = [];

    #[Override]
    public function register(Engine $engine): void
    {
        $engine->registerFunction('activeProject', [$this, 'getActiveProject']);
        $engine->registerFunction('getTeamProjects', [$this, 'getTeamProjects']);
    }

    public function getActiveProject(): Project|null
    {
        return $this->activeProject;
    }

    /** @return Project[] */
    public function getTeamProjects(): array
    {
        return $this->teamProjects;
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $active              = $request->getAttribute('active_project');
        $this->activeProject = $active instanceof Project ? $active : null;
        $projects            = $request->getAttribute('team_projects');
        /** @var Project[] $projects */
        $this->teamProjects = is_array($projects) ? $projects : [];

        try {
            return $handler->handle($request);
        } finally {
            $this->activeProject = null;
            $this->teamProjects  = [];
        }
    }
}
