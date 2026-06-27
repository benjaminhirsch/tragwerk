<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Service;

use Tragwerk\Domain\Entity\Domain;

use function preg_replace;
use function reset;
use function strtolower;

/**
 * Resolves project-level domains into concrete hosts per environment.
 *
 * Pro Placeholder gilt: existiert mindestens eine explizite Domain, gewinnt diese und wird
 * auf jedes Environment angewendet. Existiert nur eine Wildcard, wird je Environment eine vom
 * Environment-Namen abgeleitete Subdomain (`<env-slug>.basis.tld`) vergeben.
 */
final readonly class DomainResolver
{
    /**
     * @param list<Domain> $domains
     *
     * @return array<string, list<string>>
     */
    public function resolveForEnvironment(array $domains, string $envName): array
    {
        $explicit = [];
        $wildcard = [];

        foreach ($domains as $domain) {
            if ($domain->isWildcard) {
                $wildcard[$domain->placeholder][] = $domain->host;
            } else {
                $explicit[$domain->placeholder][] = $domain->host;
            }
        }

        $resolved = $explicit;
        $slug     = $this->slug($envName);

        foreach ($wildcard as $placeholder => $bases) {
            if (isset($resolved[$placeholder])) {
                // Explicit domain assigned to this placeholder wins — wildcard ignored.
                continue;
            }

            foreach ($bases as $base) {
                $resolved[$placeholder][] = $slug . '.' . $base;
            }
        }

        return $resolved;
    }

    /**
     * The canonical host for an environment: the explicit primary domain if one exists,
     * otherwise the first resolved host (placeholder `default` preferred).
     *
     * @param list<Domain> $domains
     */
    public function primaryHost(array $domains, string $envName): string|null
    {
        foreach ($domains as $domain) {
            if (! $domain->isWildcard && $domain->isPrimary) {
                return $domain->host;
            }
        }

        $resolved = $this->resolveForEnvironment($domains, $envName);

        if (isset($resolved['default'][0])) {
            return $resolved['default'][0];
        }

        $first = reset($resolved);

        return $first === false ? null : ($first[0] ?? null);
    }

    private function slug(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }
}
