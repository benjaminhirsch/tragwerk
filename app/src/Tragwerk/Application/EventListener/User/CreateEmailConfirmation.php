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

final readonly class CreateEmailConfirmation
{
    public function __construct(
        private EmailConfirmationRepository $emailConfirmationRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(Event\UserRegistered $event): void
    {
        $token = bin2hex(random_bytes(32));

        $confirmation = new EmailConfirmation(
            id: EmailConfirmationIdentifier::create(),
            userId: $event->user->id,
            token: $token,
            expiresAt: TimestampImmutable::fromDateTime(
                (new DateTimeImmutable())->add(new DateInterval('PT24H')),
            ),
            createdAt: TimestampImmutable::now(),
        );

        $this->emailConfirmationRepository->create($confirmation);
        $this->eventDispatcher->dispatch(new Event\EmailConfirmationCreated($confirmation, $event->user));
    }
}
