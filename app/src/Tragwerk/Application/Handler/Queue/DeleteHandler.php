<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Queue;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Domain\Event\QueueMessageDeleted;

use function is_string;

final readonly class DeleteHandler implements RequestHandlerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getAttribute('id');

        if (is_string($id)) {
            $this->eventDispatcher->dispatch(new QueueMessageDeleted($id));
        }

        return new RedirectResponse($this->urlHelper->generate('queue'));
    }
}
