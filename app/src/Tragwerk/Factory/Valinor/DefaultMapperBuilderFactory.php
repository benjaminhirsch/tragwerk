<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Valinor;

use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\Mapper\Http\HttpRequest;
use CuyZ\Valinor\MapperBuilder;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ServerRequestInterface;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;

final class DefaultMapperBuilderFactory
{
    public function __invoke(): MapperBuilder
    {
        return new MapperBuilder()
            ->registerConverter(self::convertServerRequestToNext(...))
            ->configureWith(new ConvertKeysToCamelCase())
            ->registerConstructor(PasswordHash::fromHash(...))
            ->registerConstructor(UserIdentifier::fromString(...))
            ->registerConstructor(TimestampImmutable::fromString(...))
            ->allowScalarValueCasting()
            ->allowUndefinedValues()
            ->allowSuperfluousKeys();
    }

    /**
     * @param callable(HttpRequest): T $next
     *
     * @return T
     *
     * @template T
     * @pure
     */
    private static function convertServerRequestToNext(ServerRequestInterface $request, callable $next): mixed
    {
        $routeResult = $request->getAttribute(RouteResult::class);

        assert($routeResult instanceof RouteResult || $routeResult === null);

        return $next(HttpRequest::fromPsr($request, $routeResult?->getMatchedParams() ?? []));
    }
}
