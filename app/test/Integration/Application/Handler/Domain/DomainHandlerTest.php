<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Handler\Domain;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Domain\Entity\Domain;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\ValueObject\DomainIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use TragwerkTest\Integration\Support\EnvironmentScopedTestCase;

use function assert;

final class DomainHandlerTest extends EnvironmentScopedTestCase
{
    #[Test]
    public function indexRendersForActiveEnvironment(): void
    {
        $response = $this->dispatch('GET', $this->url('domain'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createGetRendersForm(): void
    {
        $response = $this->dispatch('GET', $this->url('domain.create'), cookie: $this->sessionCookie);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createPostWithInvalidHostReRendersForm(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('domain.create'),
            ['host' => 'not a valid host', 'placeholder' => 'default'],
            $this->sessionCookie,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function createWildcardDomainPersists(): void
    {
        $response = $this->dispatch(
            'POST',
            $this->url('domain.create'),
            ['host' => 'preview.example.com', 'placeholder' => 'default', 'is_wildcard' => '1'],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());

        $repo = $this->container->get(DomainRepository::class);
        assert($repo instanceof DomainRepository);
        $domains = $repo->findByProject($this->project->id);

        self::assertCount(1, $domains);
        self::assertTrue($domains[0]->isWildcard);
        self::assertSame('preview.example.com', $domains[0]->host);
    }

    #[Test]
    public function deleteRemovesDomainAndRedirects(): void
    {
        $domain = $this->seedDomain('example.com', true);

        $response = $this->dispatch(
            'POST',
            $this->url('domain.delete', ['domainId' => $domain->id->toString()]),
            [],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());
        self::assertSame($this->url('domain'), $response->getHeaderLine('Location'));

        $repo = $this->container->get(DomainRepository::class);
        assert($repo instanceof DomainRepository);
        self::assertCount(0, $repo->findByProject($this->project->id));
    }

    #[Test]
    public function setPrimarySwitchesPrimaryDomain(): void
    {
        $primary   = $this->seedDomain('primary.com', true);
        $secondary = $this->seedDomain('secondary.com', false);

        $response = $this->dispatch(
            'POST',
            $this->url('domain.primary', ['domainId' => $secondary->id->toString()]),
            [],
            $this->sessionCookie,
        );

        self::assertSame(302, $response->getStatusCode());

        $repo = $this->container->get(DomainRepository::class);
        assert($repo instanceof DomainRepository);
        $byHost = [];
        foreach ($repo->findByProject($this->project->id) as $d) {
            $byHost[$d->host] = $d->isPrimary;
        }

        self::assertTrue($byHost['secondary.com']);
        self::assertFalse($byHost['primary.com']);
    }

    private function seedDomain(string $host, bool $isPrimary): Domain
    {
        $domain = new Domain(
            DomainIdentifier::create(),
            $this->project->id,
            $host,
            $isPrimary,
            TimestampImmutable::now(),
            'default',
        );

        $repo = $this->container->get(DomainRepository::class);
        assert($repo instanceof DomainRepository);
        $repo->create($domain);

        return $domain;
    }
}
