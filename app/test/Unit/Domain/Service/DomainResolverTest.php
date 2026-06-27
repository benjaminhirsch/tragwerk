<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Domain\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tragwerk\Domain\Entity\Domain;
use Tragwerk\Domain\Service\DomainResolver;
use Tragwerk\Domain\ValueObject\DomainIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

final class DomainResolverTest extends TestCase
{
    private DomainResolver $resolver;
    private ProjectIdentifier $projectId;

    protected function setUp(): void
    {
        $this->resolver  = new DomainResolver();
        $this->projectId = ProjectIdentifier::create();
    }

    #[Test]
    public function explicitDomainIsUsedVerbatimForEveryEnvironment(): void
    {
        $domains = [$this->domain('example.com', placeholder: 'default')];

        self::assertSame(
            ['default' => ['example.com']],
            $this->resolver->resolveForEnvironment($domains, 'main'),
        );
        self::assertSame(
            ['default' => ['example.com']],
            $this->resolver->resolveForEnvironment($domains, 'feature/x'),
        );
    }

    #[Test]
    public function wildcardDerivesSubdomainPerEnvironment(): void
    {
        $domains = [$this->domain('preview.example.com', placeholder: 'default', isWildcard: true)];

        self::assertSame(
            ['default' => ['main.preview.example.com']],
            $this->resolver->resolveForEnvironment($domains, 'main'),
        );
        // The environment name is slugged DNS-safe: whitespace/underscores/slashes become
        // dashes, every other non-[a-z0-9-] char is dropped.
        self::assertSame(
            ['default' => ['feature-x.preview.example.com']],
            $this->resolver->resolveForEnvironment($domains, 'feature-x'),
        );
        self::assertSame(
            ['default' => ['feature-x.preview.example.com']],
            $this->resolver->resolveForEnvironment($domains, 'feature/x'),
        );
    }

    #[Test]
    public function explicitDomainWinsOverWildcardForSamePlaceholder(): void
    {
        $domains = [
            $this->domain('example.com', placeholder: 'default'),
            $this->domain('preview.example.com', placeholder: 'default', isWildcard: true),
        ];

        self::assertSame(
            ['default' => ['example.com']],
            $this->resolver->resolveForEnvironment($domains, 'feature/x'),
        );
    }

    #[Test]
    public function placeholdersAreResolvedIndependently(): void
    {
        $domains = [
            $this->domain('api.example.com', placeholder: 'api'),
            $this->domain('preview.example.com', placeholder: 'frontend', isWildcard: true),
        ];

        self::assertSame(
            [
                'api'      => ['api.example.com'],
                'frontend' => ['feat.preview.example.com'],
            ],
            $this->resolver->resolveForEnvironment($domains, 'feat'),
        );
    }

    #[Test]
    public function multipleExplicitHostsPerPlaceholderAreAllKept(): void
    {
        $domains = [
            $this->domain('example.com', placeholder: 'default'),
            $this->domain('www.example.com', placeholder: 'default'),
        ];

        self::assertSame(
            ['default' => ['example.com', 'www.example.com']],
            $this->resolver->resolveForEnvironment($domains, 'main'),
        );
    }

    #[Test]
    public function noDomainsYieldsEmptyResult(): void
    {
        self::assertSame([], $this->resolver->resolveForEnvironment([], 'main'));
    }

    #[Test]
    public function primaryHostPrefersExplicitPrimaryDomain(): void
    {
        $domains = [
            $this->domain('secondary.com', placeholder: 'default'),
            $this->domain('primary.com', placeholder: 'default', isPrimary: true),
        ];

        self::assertSame('primary.com', $this->resolver->primaryHost($domains, 'feature/x'));
    }

    #[Test]
    public function primaryHostFallsBackToDerivedWildcard(): void
    {
        $domains = [$this->domain('preview.example.com', placeholder: 'default', isWildcard: true)];

        self::assertSame('feature-x.preview.example.com', $this->resolver->primaryHost($domains, 'feature-x'));
    }

    #[Test]
    public function primaryHostIsNullWithoutDomains(): void
    {
        self::assertNull($this->resolver->primaryHost([], 'main'));
    }

    private function domain(
        string $host,
        string $placeholder = 'default',
        bool $isPrimary = false,
        bool $isWildcard = false,
    ): Domain {
        return new Domain(
            id:          DomainIdentifier::create(),
            projectId:   $this->projectId,
            host:        $host,
            isPrimary:   $isPrimary,
            createdAt:   TimestampImmutable::now(),
            placeholder: $placeholder,
            isWildcard:  $isWildcard,
        );
    }
}
