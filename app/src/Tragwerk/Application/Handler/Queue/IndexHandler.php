<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Queue;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Repository\QueueMessageRepository;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private QueueMessageRepository $queueMessageRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $messages = $this->queueMessageRepository->getAll();

        return $this->renderer->render($request, 'page::queue/index', ['messages' => $messages]);
    }
}
