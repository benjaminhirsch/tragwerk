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
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\DomainAdded;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\DomainIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Dns\DnsResolver;

use function assert;
use function filter_var;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_IP;

final readonly class AddDomainHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectRepository $projectRepository,
        private DomainRepository $domainRepository,
        private ServerRepository $serverRepository,
        private DnsResolver $dnsResolver,
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

        $body = $request->getParsedBody();
        $host = is_array($body) && is_string($body['host'] ?? null) ? trim(strtolower($body['host'])) : '';

        $error = $this->validate($host, $project->id) ?? $this->checkDns($host, $project->serverId);

        if ($error === null) {
            $existing = $this->domainRepository->findByProject($project->id);
            $domain   = new Domain(
                id:        DomainIdentifier::create(),
                projectId: $project->id,
                host:      $host,
                isPrimary: $existing === [],
                createdAt: TimestampImmutable::now(),
            );
            $this->eventDispatcher->dispatch(new DomainAdded($domain));
        }

        return $this->renderList($request, $project, $error);
    }

    private function validate(string $host, ProjectIdentifier $projectId): string|null
    {
        if ($host === '') {
            return 'Domain darf nicht leer sein.';
        }

        if (preg_match('/^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $host) !== 1) {
            return 'Ungültige Domain (z. B. example.com oder sub.example.com).';
        }

        foreach ($this->domainRepository->findByProject($projectId) as $existing) {
            if ($existing->host === $host) {
                return 'Diese Domain ist bereits hinzugefügt.';
            }
        }

        return null;
    }

    private function checkDns(string $host, ServerIdentifier $serverId): string|null
    {
        try {
            $server = $this->serverRepository->getById($serverId);
        } catch (Throwable) {
            return null;
        }

        assert($server instanceof Server);

        if (filter_var($server->host, FILTER_VALIDATE_IP) !== false) {
            $serverIp = $server->host;
        } else {
            $serverIp = $this->dnsResolver->toIpv4($server->host);
            if ($serverIp === null) {
                return null; // server hostname can't be resolved — skip check
            }
        }

        $resolvedIp = $this->dnsResolver->toIpv4($host);

        if ($resolvedIp === null) {
            return 'Domain konnte nicht aufgelöst werden. Bitte A-Record oder CNAME setzen.';
        }

        if ($resolvedIp !== $serverIp) {
            return sprintf(
                'Domain zeigt auf %s, erwartet wird %s (Server). Bitte DNS-Eintrag aktualisieren.',
                $resolvedIp,
                $serverIp,
            );
        }

        return null;
    }

    private function renderList(
        ServerRequestInterface $request,
        Project $project,
        string|null $error,
    ): ResponseInterface {
        return $this->renderer->render($request, 'partial::project/domain-list', [
            'project' => $project,
            'domains' => $this->domainRepository->findByProject($project->id),
            'error'   => $error,
        ]);
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
