<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Event;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\ImmutableEventDispatcher;
use Tragwerk\Factory\Exception\DependencyNotFoundInServiceContainer;

use function assert;
use function count;
use function is_array;
use function is_callable;
use function is_string;

final class DispatcherFactory
{
    public function __invoke(ContainerInterface $container): EventDispatcherInterface
    {
        $config = $container->get('config');
        assert(is_array($config));

        $events = $config['events'] ?? [];
        assert(is_array($events));

        $eventDispatcher = new EventDispatcher();

        foreach ($events as $event => $listenerClasses) {
            assert(is_string($event));

            assert(is_array($listenerClasses));
            $priority = count($listenerClasses);
            foreach ($listenerClasses as $listenerClass) {
                assert(is_string($listenerClass));
                if (! $container->has($listenerClass)) {
                    throw DependencyNotFoundInServiceContainer::create($listenerClass);
                }

                $lazyListener = static function (object $event) use ($container, $listenerClass): void {
                    $listener = $container->get($listenerClass);
                    assert(is_callable($listener));

                    $listener($event);
                };

                $eventDispatcher->addListener($event, $lazyListener, $priority--);
            }
        }

        return new ImmutableEventDispatcher($eventDispatcher);
    }
}
