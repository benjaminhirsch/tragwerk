<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Variables;

use AppendIterator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Helper\ListHelper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\EnvVarRepository;
use Tragwerk\Infrastructure\Git\BareRepository;

use function assert;
use function is_string;
use function iterator_to_array;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private EnvVarRepository $envVarRepository,
        private BareRepository $bareRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeProject = $request->getAttribute('active_project');
        assert($activeProject instanceof Project);

        $activeBranch = $request->getAttribute('active_environment');
        assert(is_string($activeBranch));

        $branchVars    = $this->envVarRepository->findByBranch($activeProject->id, $activeBranch);
        $inheritedVars = $this->envVarRepository->findInheritedFromAncestors(
            $activeProject->id,
            $this->bareRepository->getBranchParents($activeProject->id->toString()),
        );

        $vars = new AppendIterator();
        $vars->append($branchVars);
        $vars->append($inheritedVars);

        return $this->renderer->render($request, 'page::variable/index', [
            'vars' => iterator_to_array(ListHelper::sort($vars, 'key')),
        ]);
    }
}
