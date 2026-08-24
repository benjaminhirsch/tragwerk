<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

final readonly class WorkerDefinitionConfig
{
    public string $command;

    public function __construct(
        public string $name,
        string|null $command = null,
        string|null $value = null,
    ) {
        // `value` is the element body (multi-line, CDATA), `command` the attribute
        // form for one-liners. ConfigValidator guarantees exactly one is present.
        $this->command = $value ?? $command ?? '';
    }
}
