<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Team;

use Laminas\I18n\Translator\Translator;
use Mezzio\Template\TemplateRendererInterface;
use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Event;

final readonly class SendTeamInvitation
{
    public function __construct(
        private Producer $producer,
        private Translator $translator,
        private TemplateRendererInterface $templateRenderer,
    ) {
    }

    public function __invoke(Event\TeamInvitationCreated $event): void
    {
        $this->producer->sendMessage(new Message\SendMail(
            to: $event->invitation->email,
            subject: $this->translator->translate('mail.teamInvitation.subject'),
            html: $this->templateRenderer->render('mail::teamInvitation', [
                'invitation' => $event->invitation,
            ]),
        ), priority: 6);
    }
}
