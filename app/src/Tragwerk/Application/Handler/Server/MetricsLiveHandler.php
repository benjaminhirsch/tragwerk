<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Server;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Infrastructure\Metrics\MetricsCollector;
use Tragwerk\Infrastructure\Ssh\RemoteShell;

use function assert;
use function explode;
use function is_array;
use function is_string;
use function json_decode;
use function trim;

final readonly class MetricsLiveHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ServerRepository $serverRepository,
        private CredentialRepository $credentialRepository,
        private MetricsCollector $collector,
        private RemoteShell $shell,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $server = $this->resolveServer($request);

        if (! $server instanceof Server) {
            return new EmptyResponse(404);
        }

        $sample     = null;
        $containers = [];
        $error      = null;

        try {
            $credential = $this->credential($server);
            $sample     = $this->collector->collect($server, $credential);
            $containers = $this->fetchContainers($server, $credential);
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        return $this->renderer->render($request, 'page::server/_metrics_live', [
            'server'     => $server,
            'sample'     => $sample,
            'containers' => $containers,
            'error'      => $error,
        ]);
    }

    private function credential(Server $server): Credential
    {
        if ($server->credentialId === null) {
            throw new RuntimeException('No credential assigned to server.');
        }

        $credential = $this->credentialRepository->getById($server->credentialId);
        assert($credential instanceof Credential);

        return $credential;
    }

    /** @return list<array<string, mixed>> */
    private function fetchContainers(Server $server, Credential $credential): array
    {
        $raw    = $this->shell->run($server, $credential, "docker stats --no-stream --format '{{json .}}' 2>&1");
        $output = trim($raw);

        if ($output === '') {
            return [];
        }

        $containers = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] !== '{') {
                continue;
            }

            /** @var array<string, mixed>|null $obj */
            $obj = json_decode($line, true);

            if (! is_array($obj) || ! isset($obj['Name'])) {
                continue;
            }

            $containers[] = $obj;
        }

        return $containers;
    }

    private function resolveServer(ServerRequestInterface $request): Server|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! ServerIdentifier::isValid($routeId)) {
            return null;
        }

        $activeTeam = $request->getAttribute('active_team');
        if (! $activeTeam instanceof Team) {
            return null;
        }

        try {
            $server = $this->serverRepository->getById(ServerIdentifier::fromString($routeId));
            assert($server instanceof Server);

            if ($server->teamId->toString() !== $activeTeam->id->toString()) {
                return null;
            }

            return $server;
        } catch (Throwable) {
            return null;
        }
    }
}
