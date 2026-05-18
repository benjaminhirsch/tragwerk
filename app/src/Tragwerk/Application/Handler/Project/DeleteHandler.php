<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function assert;
use function is_array;
use function is_string;

final readonly class DeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $this->resolveProject($request);

        if ($project instanceof Project) {
            $this->projectRepository->delete($project->id);
        }

        return new RedirectResponse($this->urlHelper->generate('project'));
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
