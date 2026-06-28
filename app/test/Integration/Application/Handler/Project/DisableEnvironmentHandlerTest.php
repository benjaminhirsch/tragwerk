<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Project;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Application\Queue\Message\StopEnvironmentDocker;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\Server;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Infrastructure\Queue\Producer as InfraProducer;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;
use TragwerkTest\Integration\Support\RecordingProducer;

use function assert;

final class DisableEnvironmentHandlerTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function disableEnqueuesStopMessageAndRedirects(): void
    {
        $credentialId = $this->seedCredentialAndAssignToServer();

        $producer = new RecordingProducer();
        $this->container->setAllowOverride(true);
        $this->container->setService(InfraProducer::class, $producer);
        $this->container->setAllowOverride(false);

        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.disable', ['id' => $this->project->id->toString()]),
            ['branch' => $this->branch],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(
            $this->url('project.show', ['id' => $this->project->id->toString()]),
            $response->getHeaderLine('Location'),
        );

        self::assertCount(1, $producer->messages);
        $message = $producer->messages[0];
        self::assertInstanceOf(StopEnvironmentDocker::class, $message);
        self::assertSame($this->project->id->toString(), $message->projectId);
        self::assertSame($this->branch, $message->branch);
        self::assertSame($this->server->host, $message->host);
        self::assertSame($credentialId->toString(), $message->credentialId);
    }

    #[Test]
    public function missingBranchReturns400(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.disable', ['id' => $this->project->id->toString()]),
            [],
            $this->sessionCookie,
        );

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function unknownBranchReturns404(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('project.environment.disable', ['id' => $this->project->id->toString()]),
            ['branch' => 'does-not-exist'],
            $this->sessionCookie,
        );

        self::assertSame(404, $response->getStatusCode());
    }

    private function seedCredentialAndAssignToServer(): CredentialIdentifier
    {
        $now          = TimestampImmutable::now();
        $credentialId = CredentialIdentifier::create();

        $credentials = $this->container->get(CredentialRepository::class);
        assert($credentials instanceof CredentialRepository);
        $credentials->create(new Credential(
            $credentialId,
            'Deploy key',
            'deploy',
            'private-key',
            $this->team->id,
            $now,
            $this->user->id,
            $now,
            $this->user->id,
        ));

        $servers = $this->container->get(ServerRepository::class);
        assert($servers instanceof ServerRepository);
        $servers->update(new Server(
            $this->server->id,
            $this->server->name,
            $this->server->host,
            $credentialId,
            $this->server->teamId,
            $this->server->createdAt,
            $this->server->createdBy,
            $now,
            $this->user->id,
            $this->server->port,
        ));

        return $credentialId;
    }
}
