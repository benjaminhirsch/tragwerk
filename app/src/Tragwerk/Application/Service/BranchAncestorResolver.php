<?php

declare(strict_types=1);

namespace Tragwerk\Application\Service;

use Throwable;
use Tragwerk\Infrastructure\Git\BareRepository;

final readonly class BranchAncestorResolver
{
    public function __construct(private BareRepository $bareRepository)
    {
    }

    /**
     * Returns ordered list of ancestor branches for the given branch, from closest to farthest.
     * Empty if the branch is a root or if the git repo is inaccessible.
     *
     * @return list<string>
     */
    public function getAncestors(string $projectId, string $branch): array
    {
        try {
            $parents = $this->bareRepository->getBranchParents($projectId);
        } catch (Throwable) {
            return [];
        }

        $ancestors = [];
        $current   = $parents[$branch] ?? null;

        while ($current !== null) {
            $ancestors[] = $current;
            $current     = $parents[$current] ?? null;
        }

        return $ancestors;
    }

    /**
     * Returns all branches that are descendants of the given branch.
     *
     * @return list<string>
     */
    public function getDescendants(string $projectId, string $branch): array
    {
        try {
            $parents = $this->bareRepository->getBranchParents($projectId);
        } catch (Throwable) {
            return [];
        }

        $descendants = [];
        foreach ($parents as $child => $parent) {
            if (! $this->isDescendantOf($child, $branch, $parents)) {
                continue;
            }

            $descendants[] = $child;
        }

        return $descendants;
    }

    /** @param array<string, string|null> $parents */
    private function isDescendantOf(string $branch, string $ancestor, array $parents): bool
    {
        $current = $parents[$branch] ?? null;
        while ($current !== null) {
            if ($current === $ancestor) {
                return true;
            }

            $current = $parents[$current] ?? null;
        }

        return false;
    }
}
