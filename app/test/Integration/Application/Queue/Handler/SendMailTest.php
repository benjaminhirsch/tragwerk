<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Queue\Handler;

use PHPUnit\Framework\Attributes\Test;
use Tragwerk\Application\Mail\Mailer;
use Tragwerk\Application\Queue\Handler\SendMail;
use Tragwerk\Application\Queue\Message;
use TragwerkTest\Integration\Support\IntegrationTestCase;
use TragwerkTest\Integration\Support\RecordingLogger;

use function assert;

final class SendMailTest extends IntegrationTestCase
{
    #[Test]
    public function handleSendsMailViaMailerAndLogs(): void
    {
        $mailer = $this->container->get(Mailer::class);
        assert($mailer instanceof Mailer);

        $logger  = new RecordingLogger();
        $handler = new SendMail($mailer, $logger);

        $handler->handle(new Message\SendMail(
            'recipient@example.com',
            'Test subject',
            '<p>Hello</p>',
        ));

        self::assertContains('Sent mail', $logger->messages);
    }
}
