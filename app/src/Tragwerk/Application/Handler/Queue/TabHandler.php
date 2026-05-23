<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Queue;

use Laminas\Diactoros\Response\EmptyResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Repository\QueueMessageRepository;

use function is_string;

final readonly class TabHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private QueueMessageRepository $queueMessageRepository,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');

        if (! is_string($id)) {
            return new EmptyResponse(200, ['HX-Redirect' => $this->urlHelper->generate('queue')]);
        }

        try {
            $message = $this->queueMessageRepository->getById($id);
        } catch (Throwable) {
            return new EmptyResponse(200, ['HX-Redirect' => $this->urlHelper->generate('queue')]);
        }

        return match ($request->getAttribute('tab')) {
            'overview' => $this->renderer->render($request, 'page::queue/tab/overview', ['message' => $message]),
            'payload'  => $this->renderer->render($request, 'page::queue/tab/payload', ['message' => $message]),
            default    => new EmptyResponse(404),
        };
    }
}
