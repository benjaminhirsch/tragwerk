<?php

declare(strict_types=1);

namespace Tragwerk\Application\Template\Extension;

use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Tragwerk\Domain\Enum\Locale as LocaleEnum;

final class Locale implements MiddlewareInterface, ExtensionInterface
{
    public LocaleEnum|null $locale = null;

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $locale = $request->getAttribute(LocaleEnum::class);

        if (! $locale instanceof LocaleEnum) {
            throw new RuntimeException('Missing locale attribute');
        }

        $this->locale = $locale;

        return $handler->handle($request);
    }

    #[Override]
    public function register(Engine $engine): void
    {
        $engine->registerFunction('getLocale', $this->getLocale(...));
    }

    public function getLocale(): string|null
    {
        return $this->locale?->value;
    }
}
