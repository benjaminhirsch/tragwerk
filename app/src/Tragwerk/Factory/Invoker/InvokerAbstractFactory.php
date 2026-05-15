<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Invoker;

use Invoker\Invoker;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\AbstractFactoryInterface;
use Override;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use RuntimeException;

use function array_diff;
use function array_keys;
use function array_map;
use function assert;
use function class_exists;
use function count;
use function implode;
use function ksort;
use function sprintf;

final readonly class InvokerAbstractFactory implements AbstractFactoryInterface
{
    /** {@inheritDoc} */
    #[Override]
    public function canCreate(ContainerInterface $container, $requestedName): bool
    {
        return class_exists($requestedName);
    }

    /** {@inheritDoc}
     *
     * @throws ReflectionException
     */
    #[Override]
    public function __invoke(ContainerInterface $container, $requestedName, array|null $options = null)
    {
        if (! class_exists($requestedName)) {
            throw new ServiceNotFoundException(sprintf(
                'Unable to create service with name %s because no such class exists',
                $requestedName,
            ));
        }

        $reflection = new ReflectionClass($requestedName);

        if (! $reflection->isInstantiable()) {
            throw new ServiceNotCreatedException(sprintf(
                'Unable to create service with name %s because it is not instantiatable',
                $requestedName,
            ));
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $invoker = $container->get(Invoker::class);
        assert($invoker instanceof Invoker);

        $resolvedParameters = $invoker->getParameterResolver()->getParameters($constructor, [], []);
        ksort($resolvedParameters);

        $reflectionParameters = $constructor->getParameters();

        if (count($resolvedParameters) !== count($reflectionParameters)) {
            $missingParameterIndexes     = array_diff(
                array_keys($reflectionParameters),
                array_keys($resolvedParameters),
            );
            $missingReflectionParameters = array_map(
                static fn (int $index) => $reflectionParameters[$index],
                $missingParameterIndexes,
            );

            throw new RuntimeException(sprintf(
                'Unable to create service with name %s because the following constructor parameters could not '
                . 'be resolved: %s',
                $requestedName,
                implode(', ', array_map(
                    static fn (ReflectionParameter $parameter) => ( $parameter->getType() ?? 'No type' )
                    . ' $'
                    . $parameter->getName(),
                    $missingReflectionParameters,
                )),
            ));
        }

        return $reflection->newInstance(...$resolvedParameters);
    }
}
