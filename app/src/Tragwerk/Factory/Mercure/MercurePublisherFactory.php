<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Mercure;

use Psr\Container\ContainerInterface;
use Tragwerk\Infrastructure\Mercure\MercurePublisher;

use function assert;
use function is_array;
use function is_string;

final readonly class MercurePublisherFactory
{
    public function __invoke(ContainerInterface $container): MercurePublisher
    {
        $config = $container->get('config');
        assert(is_array($config));

        $hubUrl    = $config['mercure']['hub_url'] ?? 'http://localhost/.well-known/mercure';
        $topicBase = $config['mercure']['topic_base'] ?? 'https://tragwerk.build';
        $secret    = $config['mercure']['publisher_jwt_secret'] ?? '';

        assert(is_string($hubUrl) && $hubUrl !== '');
        assert(is_string($topicBase) && $topicBase !== '');
        assert(is_string($secret));

        return new MercurePublisher($hubUrl, $topicBase, $secret);
    }
}
