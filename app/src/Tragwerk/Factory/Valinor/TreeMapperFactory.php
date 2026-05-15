<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Valinor;

use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

use function assert;

final class TreeMapperFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): TreeMapper
    {
        $mapper = $container->get(MapperBuilder::class);

        assert($mapper instanceof MapperBuilder);

        return $mapper->mapper();
    }
}
