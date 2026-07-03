<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler;

use Laminas\Diactoros\Response\TextResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function file_get_contents;
use function sprintf;

/**
 * Serves the config.xml XSD publicly so a project's config.xml can reference it
 * via xsi:noNamespaceSchemaLocation (e.g. https://console.tragwerk.app/schema.xsd).
 * Reads the canonical schema shipped with the app — single source of truth,
 * also consumed by ConfigValidator.
 */
final readonly class SchemaHandler implements RequestHandlerInterface
{
    private const string SCHEMA_PATH = __DIR__ . '/../../Domain/Config/schema.xsd';

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $schema = file_get_contents(self::SCHEMA_PATH);

        if ($schema === false) {
            throw new RuntimeException(sprintf('Unable to read schema at %s', self::SCHEMA_PATH));
        }

        return new TextResponse($schema, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
