<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Domain;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
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
use Tragwerk\Domain\Event\DomainAdded;
use Tragwerk\Domain\Repository\DomainRepository;
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

final readonly class CreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private DomainRepository $domainRepository,
        private ServerRepository $serverRepository,
        private DnsResolver $dnsResolver,
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

        $host        = '';
        $placeholder = 'default';
        $error       = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $body        = $request->getParsedBody();
            $host        = is_array($body) && is_string($body['host'] ?? null) ? trim(strtolower($body['host'])) : '';
            $placeholder = is_array($body) && is_string($body['placeholder'] ?? null)
                ? trim(strtolower($body['placeholder']))
                : 'default';

            if ($placeholder === '' || preg_match('/^[a-z][a-z0-9_-]*$/', $placeholder) !== 1) {
                $placeholder = 'default';
            }

            $error = $this->validate($host, $project->id, $branch) ?? $this->checkDns($host, $project->serverId);

            if ($error === null) {
                $existing = $this->domainRepository->findByEnvironment($project->id, $branch);
                $domain   = new Domain(
                    id:          DomainIdentifier::create(),
                    projectId:   $project->id,
                    host:        $host,
                    isPrimary:   $existing === [],
                    createdAt:   TimestampImmutable::now(),
                    placeholder: $placeholder,
                    branch:      $branch,
                );
                $this->eventDispatcher->dispatch(new DomainAdded($domain));

                return new RedirectResponse($this->urlHelper->generate('domain'));
            }
        }

        return $this->renderer->render($request, 'page::domain/create', [
            'branch'      => $branch,
            'host'        => $host,
            'placeholder' => $placeholder,
            'error'       => $error,
        ]);
    }

    private function validate(string $host, ProjectIdentifier $projectId, string $branch): string|null
    {
        if ($host === '') {
            return 'Domain must not be empty.';
        }

        if (preg_match('/^([a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $host) !== 1) {
            return 'Invalid domain (e.g. example.com or sub.example.com).';
        }

        foreach ($this->domainRepository->findByEnvironment($projectId, $branch) as $existing) {
            if ($existing->host === $host) {
                return 'This domain has already been added.';
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
                return null;
            }
        }

        $resolvedIp = $this->dnsResolver->toIpv4($host);

        if ($resolvedIp === null) {
            return 'Domain could not be resolved. Please set an A record or CNAME.';
        }

        if ($resolvedIp !== $serverIp) {
            return sprintf(
                'Domain points to %s, expected %s (server). Please update the DNS record.',
                $resolvedIp,
                $serverIp,
            );
        }

        return null;
    }
}
