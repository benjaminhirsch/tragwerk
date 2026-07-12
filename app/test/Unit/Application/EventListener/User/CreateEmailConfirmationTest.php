<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\EventListener\User;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Application\EventListener\User\CreateEmailConfirmation;
use Tragwerk\Domain\Entity\EmailConfirmation;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Event\EmailConfirmationCreated;
use Tragwerk\Domain\Event\UserRegistered;
use Tragwerk\Domain\Repository\EmailConfirmationRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

final class CreateEmailConfirmationTest extends TestCase
{
    #[Test]
    public function createsAConfirmationAndDispatchesTheFollowUpEvent(): void
    {
        $repository = $this->createMock(EmailConfirmationRepository::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $user       = $this->user();

        $repository->expects(self::once())
            ->method('create')
            ->with(self::callback(
                static fn (EmailConfirmation $confirmation): bool => $confirmation->userId->isEqualTo($user->id),
            ));

        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(EmailConfirmationCreated::class));

        new CreateEmailConfirmation($repository, $dispatcher)(new UserRegistered($user));
    }

    #[Test]
    public function doesNothingWhenTheEventOptsOutOfEmailConfirmation(): void
    {
        $repository = $this->createMock(EmailConfirmationRepository::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $repository->expects(self::never())->method('create');
        $dispatcher->expects(self::never())->method('dispatch');

        new CreateEmailConfirmation($repository, $dispatcher)(
            new UserRegistered($this->user(), requiresEmailConfirmation: false),
        );
    }

    private function user(): User
    {
        $now = TimestampImmutable::now();

        return new User(
            UserIdentifier::create(),
            'ada@example.com',
            'Ada',
            'Lovelace',
            PasswordHash::create('secure-password-123'),
            $now,
            $now,
        );
    }
}
