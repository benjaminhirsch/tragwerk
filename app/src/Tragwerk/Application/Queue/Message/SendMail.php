<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Message;

use Tragwerk\Application\Queue\Message;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final readonly class SendMail implements Message
{
    public function __construct(
        public string $to,
        public string $subject,
        public string|null $text,
        public UserIdentifier|null $sender = null,
        public UserIdentifier|null $issuerId = null,
    ) {
    }
}
