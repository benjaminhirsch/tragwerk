<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use DateInterval;
use DateTimeImmutable;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Domain\Entity\EmailConfirmation;
use Tragwerk\Domain\Event;
use Tragwerk\Domain\Repository\EmailConfirmationRepository;
use Tragwerk\Domain\ValueObject\EmailConfirmationIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function bin2hex;
use function random_bytes;

final readonly class CreateEmailChangeConfirmation
{
    public function __construct(
        private EmailConfirmationRepository $emailConfirmationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(Event\EmailChangeRequested $event): void
    {
        $confirmation = new EmailConfirmation(
            id: EmailConfirmationIdentifier::create(),
            userId: $event->user->id,
            token: bin2hex(random_bytes(32)),
            expiresAt: TimestampImmutable::fromDateTime(
                (new DateTimeImmutable())->add(new DateInterval('PT24H')),
            ),
            createdAt: TimestampImmutable::now(),
            newEmail: $event->newEmail,
        );

        $this->emailConfirmationRepository->create($confirmation);
        $this->eventDispatcher->dispatch(
            new Event\EmailChangeConfirmationCreated($confirmation, $event->user, $event->newEmail),
        );
    }
}
