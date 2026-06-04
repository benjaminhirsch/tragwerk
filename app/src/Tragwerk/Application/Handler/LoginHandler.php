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
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\UserRepository;

use function assert;
use function is_array;
use function is_string;

final readonly class LoginHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private AuthenticationInterface $authentication,
        private UserRepository $userRepository,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        assert($session instanceof SessionInterface);

        $queryParams = $request->getQueryParams();

        if ($request->getMethod() === 'POST') {
            return $this->handleLogin($request, $session);
        }

        return $this->renderer->render($request, 'page::login', [
            'registered'     => isset($queryParams['registered']),
            'confirmed'      => isset($queryParams['confirmed']),
            'resetRequested' => isset($queryParams['reset-requested']),
            'passwordReset'  => isset($queryParams['password-reset']),
        ]);
    }

    public function handleLogin(
        ServerRequestInterface $request,
        SessionInterface $session,
    ): ResponseInterface {
        $session->unset(UserInterface::class);

        $body = $request->getParsedBody();
        assert(is_array($body));
        $email = is_string($body['email'] ?? null) ? $body['email'] : '';

        if ($this->authentication->authenticate($request) !== null) {
            try {
                $user = $this->userRepository->getByEmail($email);
                if ($user->confirmedAt === null) {
                    $session->unset(UserInterface::class);

                    return $this->renderer->render($request, 'page::login', ['notConfirmed' => true]);
                }
            } catch (EntityNotFound) {
            }

            return new RedirectResponse($this->urlHelper->generate('home'));
        }

        return $this->renderer->render($request, 'page::login', ['loginError' => true]);
    }
}
