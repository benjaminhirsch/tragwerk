<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use Mezzio\Template\TemplateRendererInterface;
use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Event;

use function _;

final readonly class SendEmailChangeMail
{
    public function __construct(
        private Producer $producer,
        private TemplateRendererInterface $templateRenderer,
    ) {
    }

    public function __invoke(Event\EmailChangeConfirmationCreated $event): void
    {
        $this->producer->sendMessage(new Message\SendMail(
            to: $event->newEmail,
            subject: _('Confirm your new email address'),
            text: $this->templateRenderer->render('mail::emailChange', [
                'user'         => $event->user,
                'confirmation' => $event->confirmation,
                'newEmail'     => $event->newEmail,
            ]),
        ), priority: 6);
    }
}
