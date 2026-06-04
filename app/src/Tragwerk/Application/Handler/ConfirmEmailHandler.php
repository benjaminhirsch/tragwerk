<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\EmailConfirmationRepository;
use Tragwerk\Domain\Repository\UserRepository;

use function assert;
use function is_string;

final readonly class ConfirmEmailHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private EmailConfirmationRepository $emailConfirmationRepository,
        private UserRepository $userRepository,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $token = $request->getAttribute('token');
        assert(is_string($token));

        try {
            $confirmation = $this->emailConfirmationRepository->getByToken($token);
        } catch (EntityNotFound) {
            return $this->renderer->render($request, 'page::confirm-email-error');
        }

        if ($confirmation->expiresAt->isPast()) {
            return $this->renderer->render($request, 'page::confirm-email-error');
        }

        $this->userRepository->confirm($confirmation->userId);

        return new RedirectResponse(
            $this->urlHelper->generate('login') . '?confirmed=1',
        );
    }
}
