<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Queue;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Repository\QueueMessageRepository;

use function is_string;

final readonly class ShowHandler implements RequestHandlerInterface
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
            return new RedirectResponse($this->urlHelper->generate('queue'));
        }

        try {
            $message = $this->queueMessageRepository->getById($id);
        } catch (Throwable) {
            return new RedirectResponse($this->urlHelper->generate('queue'));
        }

        return $this->renderer->render($request, 'page::queue/show', ['message' => $message]);
    }
}
