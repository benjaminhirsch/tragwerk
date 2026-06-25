<?php

declare(strict_types=1);

namespace Tragwerk\Application\EventListener\Team;

use Laminas\I18n\Translator\Translator;
use Mezzio\Template\TemplateRendererInterface;
use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Domain\Event;

final readonly class SendTeamMemberAddedNotification
{
    public function __construct(
        private Producer $producer,
        private Translator $translator,
        private TemplateRendererInterface $templateRenderer,
    ) {
    }

    public function __invoke(Event\TeamMemberAdded $event): void
    {
        $this->producer->sendMessage(new Message\SendMail(
            to: $event->user->email,
            subject: $this->translator->translate('You have been added to a team on Tragwerk.'),
            text: $this->templateRenderer->render('mail::teamMemberAdded', [
                'team' => $event->team,
                'user' => $event->user,
            ]),
        ), priority: 6);
    }
}
