<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

/**
 * Identity of the deployment being generated. Everything a deployed application needs to know
 * about the platform it runs on, minus the parts the generators derive themselves (application
 * name, routes, relationships).
 *
 * Optional throughout the generators: the offline `tragwerk:generate-docker` command has no
 * deployment behind it and passes null, which keeps its output free of platform variables.
 */
final readonly class PlatformContext
{
    public function __construct(
        public string $projectId,
        public string $projectName,
        public string $projectEntropy,
        public string $branch,
        public string $commitSha,
        public string|null $deployId = null,
    ) {
    }
}
