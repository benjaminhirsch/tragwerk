<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Domain;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Domain\Entity\Domain;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Event\DomainDeleted;
use Tragwerk\Domain\Event\DomainSetPrimary;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\ValueObject\DomainIdentifier;

use function assert;
use function is_string;

final readonly class DeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private DomainRepository $domainRepository,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $request->getAttribute('active_project');
        assert($project instanceof Project);

        $branch = $request->getAttribute('active_environment');
        assert(is_string($branch));

        $domain = $this->resolveDomain($request, $project, $branch);

        if ($domain instanceof Domain) {
            $wasPrimary = $domain->isPrimary;
            $this->eventDispatcher->dispatch(new DomainDeleted($domain->id, $project->id, $branch));

            if ($wasPrimary) {
                $remaining = $this->domainRepository->findByEnvironment($project->id, $branch);
                if ($remaining !== []) {
                    $this->eventDispatcher->dispatch(new DomainSetPrimary($remaining[0]->id, $project->id, $branch));
                }
            }
        }

        return new RedirectResponse($this->urlHelper->generate('domain'));
    }

    private function resolveDomain(ServerRequestInterface $request, Project $project, string $branch): Domain|null
    {
        $domainId = $request->getAttribute('domainId');
        if (! is_string($domainId) || ! DomainIdentifier::isValid($domainId)) {
            return null;
        }

        try {
            $domain = $this->domainRepository->getById(DomainIdentifier::fromString($domainId));
        } catch (Throwable) {
            return null;
        }

        if ($domain->projectId->toString() !== $project->id->toString() || $domain->branch !== $branch) {
            return null;
        }

        return $domain;
    }
}
