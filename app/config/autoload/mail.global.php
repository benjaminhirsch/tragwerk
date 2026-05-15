<?php

declare(strict_types=1);

use Symfony\Component\Mailer\Transport as MailTransport;

$sendmailPath = ini_get('sendmail_path');

if (is_string(getenv('SENDMAIL_PATH'))) {
    $sendmailPath = getenv('SENDMAIL_PATH');
}

$smtpPort = getenv('SMTP_PORT');
if (is_string($smtpPort)) {
    $smtpPort = (int) $smtpPort;
}

return [
    'mail' => [
        'transport' => MailTransport\Smtp\EsmtpTransport::class,
        'transports' => [
            MailTransport\SendmailTransport::class => ['command' => $sendmailPath],
            MailTransport\Smtp\EsmtpTransport::class => [
                'host' => getenv('SMTP_HOST'),
                'port' => $smtpPort,
                'localDomain' => getenv('SMTP_LOCAL_DOMAIN'),
                'username' => getenv('SMTP_USERNAME'),
                'password' => getenv('SMTP_PASSWORD'),
            ],
        ],
        'defaultEmail' => [
            'from' => ['no-reply@luminario.de' => 'Tragwerk'],
        ],
    ],
];
