<?php

declare(strict_types=1);

namespace TragwerkTest\Unit\Application\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Middleware\ParseRawJsonBody;

final class ParseRawJsonBodyTest extends TestCase
{
    private Psr17Factory $factory;
    private ParseRawJsonBody $middleware;

    protected function setUp(): void
    {
        $this->factory    = new Psr17Factory();
        $this->middleware = new ParseRawJsonBody();
    }

    private function jsonRequest(string $body): ServerRequestInterface
    {
        return $this->factory->createServerRequest('POST', '/webhooks/git-push')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($body));
    }

    /**
     * Runs the middleware and returns the parsed body the downstream handler received.
     *
     * @return array<mixed>|object|null
     */
    private function captureParsedBody(ServerRequestInterface $request): array|object|null
    {
        $handler = new class implements RequestHandlerInterface {
            /** @var array<mixed>|object|null */
            public array|object|null $captured = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->captured = $request->getParsedBody();

                return new Psr17Factory()->createResponse(200);
            }
        };

        $this->middleware->process($request, $handler);

        return $handler->captured;
    }

    #[Test]
    public function parsesJsonWhenParsedBodyIsEmptyArray(): void
    {
        // FrankenPHP/SAPI sets the parsed body to $_POST === [] for JSON requests.
        $request = $this->jsonRequest('{"projectId":"abc","branch":"main"}')->withParsedBody([]);

        self::assertSame(['projectId' => 'abc', 'branch' => 'main'], $this->captureParsedBody($request));
    }

    #[Test]
    public function parsesJsonWhenParsedBodyIsNull(): void
    {
        $request = $this->jsonRequest('{"branch":"main"}');

        self::assertSame(['branch' => 'main'], $this->captureParsedBody($request));
    }

    #[Test]
    public function skipsWhenParsedBodyAlreadyPopulated(): void
    {
        $request = $this->jsonRequest('{"branch":"main"}')->withParsedBody(['existing' => 'value']);

        self::assertSame(['existing' => 'value'], $this->captureParsedBody($request));
    }

    #[Test]
    public function skipsWhenNotJson(): void
    {
        $request = $this->factory->createServerRequest('POST', '/')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withParsedBody([])
            ->withBody($this->factory->createStream('{"branch":"main"}'));

        self::assertSame([], $this->captureParsedBody($request));
    }

    #[Test]
    public function returns400OnInvalidJson(): void
    {
        $request = $this->jsonRequest('{not valid json');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects(self::never())->method('handle');

        $response = $this->middleware->process($request, $handler);

        self::assertSame(400, $response->getStatusCode());
    }
}
