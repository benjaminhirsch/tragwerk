<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\User;

use Laminas\I18n\Translator\Translator;
use Mezzio\Template\TemplateRendererInterface;
use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Event;

final readonly class SendPasswordResetMail
{
    public function __construct(
        private Producer $producer,
        private Translator $translator,
        private TemplateRendererInterface $templateRenderer,
    ) {
    }

    public function __invoke(Event\PasswordResetRequested $event): void
    {
        $this->producer->sendMessage(new Message\SendMail(
            to: $event->user->email,
            subject: $this->translator->translate('mail.passwordReset.subject'),
            text: $this->templateRenderer->render('mail::passwordReset', [
                'user'          => $event->user,
                'passwordReset' => $event->passwordReset,
            ]),
        ), priority: 6);
    }
}
