<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;

use function array_splice;
use function array_values;
use function is_array;
use function is_numeric;
use function is_string;

final readonly class EmailFieldsHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body      = $request->getParsedBody();
        $rawEmails = is_array($body) && is_array($body['emailsToInvite'] ?? null)
            ? $body['emailsToInvite']
            : [];
        $emails    = array_values($rawEmails);
        $action    = is_array($body) && is_string($body['action'] ?? null) ? $body['action'] : 'add';

        if ($action === 'add') {
            $emails[] = '';
        } elseif ($action === 'remove') {
            $removeIndex = is_array($body) ? ($body['removeIndex'] ?? -1) : -1;
            if (is_numeric($removeIndex)) {
                array_splice($emails, (int) $removeIndex, 1);
            }
        }

        if ($emails === []) {
            $emails = [''];
        }

        return $this->renderer->render($request, 'partial::team/email-fields', ['emails' => $emails]);
    }
}
