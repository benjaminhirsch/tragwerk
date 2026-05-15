<?php

declare(strict_types=1);

namespace Tragwerk\Application\Logger;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use Tragwerk\Application\Helper\ThrowableHelper;

final readonly class LoggingErrorListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(
        Throwable $throwable,
        RequestInterface $serverRequest,
        ResponseInterface $response,
    ): void {
        $this->logger->error($throwable->getMessage(), [
            'exception' => ThrowableHelper::toArray($throwable),
            'request'   => $this->normalizeRequest($serverRequest),
        ]);
    }

    /**
     * @return mixed[]
     *
     * @psalm-pure
     */
    private function normalizeRequest(RequestInterface $request): array
    {
        $referrer = $request->getHeaderLine('Referer');

        return [
            'http_version' => $request->getProtocolVersion(),
            'method'       => $request->getMethod(),
            'target'       => $request->getRequestTarget(),
            'referrer'     => $referrer === '' ? null : $referrer,
        ];
    }
}
