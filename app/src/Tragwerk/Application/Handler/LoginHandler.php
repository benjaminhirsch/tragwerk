<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;

use function assert;

final readonly class LoginHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private AuthenticationInterface $authentication,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        assert($session instanceof SessionInterface);

        if ($request->getMethod() === 'POST') {
            return $this->handleLogin($request, $session);
        }

        return $this->renderer->render($request, 'page::login');
    }

    public function handleLogin(
        ServerRequestInterface $request,
        SessionInterface $session,
    ): ResponseInterface {
        $session->unset(UserInterface::class);

        if ($this->authentication->authenticate($request) !== null) {
            return new RedirectResponse($this->urlHelper->generate('home'));
        }

        return $this->renderer->render($request, 'page::login');
    }
}
