<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service;

use Tragwerk\Domain\Repository\EnvVarRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;

use function array_flip;
use function iterator_to_array;
use function usort;

use const PHP_INT_MAX;

/**
 * Resolves the effective env var map for an environment: branch-specific vars plus vars inherited
 * from ancestor branches (is_inherited). Used by both the build (bakes them into the build.zip
 * docker-compose.yml) and the deploy (regenerates docker-compose.yml with image: refs) so the two
 * cannot drift — if they did, the deploy's compose would silently win and drop inherited vars.
 */
final readonly class EnvVarResolver
{
    public function __construct(
        private EnvVarRepository $envVarRepository,
        private BranchAncestorResolver $ancestorResolver,
    ) {
    }

    /**
     * Branch-specific vars override inherited; among inherited, the closer ancestor wins.
     *
     * @return array<string, string>
     */
    public function resolve(ProjectIdentifier $projectId, string $branch): array
    {
        $ancestors     = $this->ancestorResolver->getAncestors($projectId->toString(), $branch);
        $inheritedVars = $this->envVarRepository->findInheritedFromAncestors($projectId, $ancestors);

        $resolved = [];

        // Inherited vars: iterate from farthest ancestor to closest so the closer ancestor wins.
        $ancestorOrder   = array_flip($ancestors);
        $sortedInherited = iterator_to_array($inheritedVars, false);
        usort($sortedInherited, static function ($a, $b) use ($ancestorOrder): int {
            return ($ancestorOrder[$b->branch] ?? PHP_INT_MAX) <=> ($ancestorOrder[$a->branch] ?? PHP_INT_MAX);
        });

        foreach ($sortedInherited as $var) {
            $resolved[$var->key] = $var->value;
        }

        // Branch-specific vars always win.
        foreach ($this->envVarRepository->findByBranch($projectId, $branch) as $var) {
            $resolved[$var->key] = $var->value;
        }

        return $resolved;
    }
}
