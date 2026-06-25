<?php

declare(strict_types=1);

namespace Tragwerk\Application\Queue\Handler;

use Psr\Log\LoggerInterface;
use Tragwerk\Application\Mail\Mailer;
use Tragwerk\Application\Queue\Message;

final class SendMail
{
    public function __construct(
        private Mailer $mailer,
        private LoggerInterface $logger,
    ) {
    }

    public function handle(Message\SendMail $message): void
    {
        $email = $this->mailer->makeEmail(
            $message->to,
            $message->subject,
            $message->text,
        );

        $this->mailer->sendEmail($email, $message->sender);

        $this->logger->info('Sent mail', [
            'subject' => $message->subject,
            'to' => $message->to,
        ]);
    }
}
