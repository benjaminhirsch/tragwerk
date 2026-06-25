<?php

declare(strict_types=1);

namespace Tragwerk\Application\Mail;

use InvalidArgumentException;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function basename;
use function count;
use function is_file;
use function is_readable;
use function is_string;
use function sprintf;

final class Mailer
{
    /** @var callable(): Email */
    private $emailFactory;

    /** @param callable(): Email $emailFactory */
    public function __construct(
        private SymfonyMailer $mailer,
        callable $emailFactory,
    ) {
        $this->emailFactory = $emailFactory;
    }

    /** @psalm-mutation-free */
    public function makeDefaultEmail(): Email
    {
        return ($this->emailFactory)();
    }

    /**
     * @param (string|DataPart)[] $attachments List of file paths or data parts to attach
     *
     * @psalm-mutation-free
     */
    public function makeEmail(
        string|Address $to,
        string $subject,
        string|null $text,
        array $attachments = [],
    ): Email {
        $email = $this->makeDefaultEmail();

        $email->to($to);
        $email->subject($subject);

        if ($text !== null) {
            $email->text($text);
        }

        foreach ($attachments as $attachmentName => $attachment) {
            if ($attachment instanceof DataPart) {
                $email->addPart($attachment);

                continue;
            }

            $attachmentPath = $attachment;

            if (! is_file($attachmentPath) || ! is_readable($attachmentPath)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid attachment path "%s": File not found or not readable',
                    $attachmentPath,
                ));
            }

            if (! is_string($attachmentName)) {
                $attachmentName = basename($attachmentPath);
            }

            $email->addPart(new DataPart(
                new File($attachmentPath),
                $attachmentName,
            ));
        }

        return $email;
    }

    public function sendEmail(Email $email, UserIdentifier|null $sender = null): void
    {
        if (count($email->getFrom()) === 0) {
            throw new InvalidArgumentException('Missing "from" data in message');
        }

        if (count($email->getTo()) === 0) {
            throw new InvalidArgumentException('Missing "to" data in message');
        }

        if ($email->getSubject() === null) {
            throw new InvalidArgumentException('Missing "subject" data in message');
        }

        $email->ensureValidity();

        $this->mailer->send($email);
    }
}
