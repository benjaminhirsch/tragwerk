<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

use Tragwerk\Domain\Enum\ApplicationRuntime;

final readonly class ApplicationConfig
{
    /**
     * @param list<HookConfig>             $hooks
     * @param list<MountConfig>            $mounts
     * @param list<RelationshipConfig>     $relationships
     * @param list<ExtensionConfig>        $extensions
     * @param list<WorkerDefinitionConfig> $workers
     */
    public function __construct(
        public string $name,
        public ApplicationRuntime $type,
        public string $root,
        public WebConfig $web,
        public array $hooks = [],
        public array $mounts = [],
        public array $relationships = [],
        public array $extensions = [],
        public WorkerConfig|null $workerMode = null,
        public array $workers = [],
    ) {
    }
}
