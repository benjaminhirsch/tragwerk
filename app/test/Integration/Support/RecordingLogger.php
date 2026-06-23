<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Support;

use Psr\Log\AbstractLogger;
use Stringable;

/** Test logger that records the rendered message of every log call. */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $messages = [];

    /** @param mixed[] $context */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->messages[] = (string) $message;
    }
}
