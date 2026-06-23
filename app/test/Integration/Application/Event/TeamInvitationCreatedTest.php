<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Event;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tragwerk\Application\Queue\Message;
use Tragwerk\Domain\Entity\TeamInvitation;
use Tragwerk\Domain\Event\TeamInvitationCreated;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\TeamInvitationIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Infrastructure\Queue\Producer as InfraProducer;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;
use TragwerkTest\Integration\Support\RecordingProducer;

use function assert;

final class TeamInvitationCreatedTest extends AppIntegrationTestCase
{
    #[Test]
    public function enqueuesInvitationMail(): void
    {
        $producer = new RecordingProducer();
        $this->container->setAllowOverride(true);
        $this->container->setService(InfraProducer::class, $producer);
        $this->container->setAllowOverride(false);

        $invitation = new TeamInvitation(
            id: TeamInvitationIdentifier::create(),
            teamId: TeamIdentifier::create(),
            email: 'invitee@example.com',
            token: 'invite-token',
            invitedAt: TimestampImmutable::now(),
            invitedBy: UserIdentifier::create(),
        );

        $this->dispatcher()->dispatch(new TeamInvitationCreated($invitation));

        self::assertCount(1, $producer->messages);
        $message = $producer->messages[0];
        self::assertInstanceOf(Message\SendMail::class, $message);
        self::assertSame('invitee@example.com', $message->to);
    }

    private function dispatcher(): EventDispatcherInterface
    {
        $dispatcher = $this->container->get(EventDispatcherInterface::class);
        assert($dispatcher instanceof EventDispatcherInterface);

        return $dispatcher;
    }
}
