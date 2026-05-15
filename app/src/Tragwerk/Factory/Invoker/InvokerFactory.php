<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Invoker;

use Invoker\Invoker;
use Invoker\ParameterResolver\AssociativeArrayResolver;
use Invoker\ParameterResolver\Container\TypeHintContainerResolver;
use Invoker\ParameterResolver\DefaultValueResolver;
use Invoker\ParameterResolver\ResolverChain;
use Invoker\ParameterResolver\TypeHintResolver;
use Psr\Container\ContainerInterface;

final readonly class InvokerFactory
{
    public function __invoke(ContainerInterface $container): Invoker
    {
        $parameterResolver = new ResolverChain([
            new AssociativeArrayResolver(),
            new TypeHintResolver(),
            new TypeHintContainerResolver($container),
            new DefaultValueResolver(),
        ]);

        return new Invoker($parameterResolver);
    }
}
