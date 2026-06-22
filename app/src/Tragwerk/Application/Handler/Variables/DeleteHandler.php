<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Variables;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Domain\Entity\EnvVar;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Event\EnvVarDeleted;
use Tragwerk\Domain\Repository\EnvVarRepository;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;

use function is_string;

final readonly class DeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private EnvVarRepository $envVarRepository,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $var = $this->resolveVariable($request);

        if ($var instanceof EnvVar) {
            $this->eventDispatcher->dispatch(new EnvVarDeleted(
                id:           $var->id,
                projectId:    $var->projectId,
                branch:       $var->branch,
                wasInherited: $var->isInherited,
            ));
        }

        return new RedirectResponse($this->urlHelper->generate('variable'));
    }

    private function resolveVariable(ServerRequestInterface $request): EnvVar|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! EnvVarIdentifier::isValid($routeId)) {
            return null;
        }

        $activeProject = $request->getAttribute('active_project');
        if (! $activeProject instanceof Project) {
            return null;
        }

        try {
            $var = $this->envVarRepository->getById(EnvVarIdentifier::fromString($routeId));

            if ($var->projectId->toString() !== $activeProject->id->toString()) {
                return null;
            }

            return $var;
        } catch (Throwable) {
            return null;
        }
    }
}
