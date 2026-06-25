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
use Tragwerk\Application\Helper\TrustedDeviceCookie;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\TwoFactor\TwoFactorSession;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\TrustedDeviceRepository;
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
        private TrustedDeviceRepository $trustedDeviceRepository,
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
            'emailChanged'   => isset($queryParams['email-changed']),
            'twoFactorError' => isset($queryParams['2fa-failed']) || isset($queryParams['2fa-expired']),
        ]);
    }

    public function handleLogin(
        ServerRequestInterface $request,
        SessionInterface $session,
    ): ResponseInterface {
        $session->unset(UserInterface::class);
        TwoFactorSession::clear($session);

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

                if ($user->hasTwoFactorEnabled() && ! $this->deviceIsTrusted($request, $user)) {
                    return $this->enterTwoFactorChallenge($session);
                }
            } catch (EntityNotFound) {
            }

            return new RedirectResponse($this->urlHelper->generate('home'));
        }

        return $this->renderer->render($request, 'page::login', ['loginError' => true]);
    }

    private function deviceIsTrusted(ServerRequestInterface $request, User $user): bool
    {
        $token = TrustedDeviceCookie::readToken($request);
        if ($token === null) {
            return false;
        }

        return $this->trustedDeviceRepository->findValidByTokenHash(
            TrustedDeviceCookie::hash($token),
            $user->id,
        ) !== null;
    }

    private function enterTwoFactorChallenge(SessionInterface $session): ResponseInterface
    {
        $payload = $session->get(UserInterface::class);
        assert(is_array($payload));

        // Revoke the full session PhpSession just granted; the user stays
        // "pending" until the second factor is confirmed.
        $session->unset(UserInterface::class);
        TwoFactorSession::begin($session, $payload);
        $session->regenerate();

        return new RedirectResponse($this->urlHelper->generate('2fa.challenge'));
    }
}
