<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Docker;

final readonly class DockerfileOutput
{
    public function __construct(
        public string $dockerfileName,
        public string $dockerfileContent,
        public string|null $entrypointName,
        public string|null $entrypointContent,
        public string|null $caddyfileName,
        public string|null $caddyfileContent,
        public string|null $crontabName = null,
        public string|null $crontabContent = null,
        public string|null $phpIniName = null,
        public string|null $phpIniContent = null,
    ) {
    }
}
