<?php

declare(strict_types=1);

namespace Tragwerk\Application\ReadModel;

use Tragwerk\Domain\ValueObject\TimestampImmutable;

/**
 * A single, display-ready item for the team activity feed. Synthesised from
 * deploy jobs, setup jobs and team invitations — there is no persisted audit log.
 */
final readonly class ActivityEntry
{
    public function __construct(
        public string $icon,
        public string $iconColorVar,
        public string $subject,
        public string $detail,
        public TimestampImmutable $occurredAt,
    ) {
    }
}
