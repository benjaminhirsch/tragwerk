<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Mail;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tragwerk\Application\Mail\Mailer;
use Tragwerk\Factory\Exception\MissingConfiguration;

use function assert;
use function is_array;
use function is_iterable;
use function is_string;

final class MailerFactory
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): Mailer
    {
        $config = $container->get('config');
        assert(is_array($config));

        $defaultEmailConfig = $config['mail']['defaultEmail'] ?? null;
        if ($defaultEmailConfig === null) {
            throw MissingConfiguration::createFromSubject('message configuration entry');
        }

        $emailFactory = static function () use ($defaultEmailConfig): Email {
            $email = new Email();

            if (isset($defaultEmailConfig['from'])) {
                assert(is_iterable($defaultEmailConfig['from']));
                foreach ($defaultEmailConfig['from'] as $fromAddress => $fromName) {
                    assert(is_string($fromAddress) && is_string($fromName));
                    $email->addFrom(new Address($fromAddress, $fromName));
                }
            }

            return $email;
        };

        $mailer = $container->get(SymfonyMailer::class);
        assert($mailer instanceof SymfonyMailer);

        return new Mailer(
            $mailer,
            $emailFactory,
        );
    }
}
