<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\User;

use DateInterval;
use DateTimeImmutable;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Helper\TrustedDeviceCookie;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\TwoFactor\TwoFactorService;
use Tragwerk\Application\Service\TwoFactor\TwoFactorSession;
use Tragwerk\Domain\Entity\TrustedDevice;
use Tragwerk\Domain\Event\RecoveryCodeConsumed;
use Tragwerk\Domain\Event\TrustedDeviceAdded;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\RecoveryCodeRepository;
use Tragwerk\Domain\Repository\UserTwoFactorRepository;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\Token;
use Tragwerk\Domain\ValueObject\TrustedDeviceIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function is_array;
use function is_string;
use function sprintf;
use function substr;

final readonly class TwoFactorChallengeHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private UserTwoFactorRepository $userTwoFactorRepository,
        private RecoveryCodeRepository $recoveryCodeRepository,
        private TwoFactorService $twoFactorService,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        assert($session instanceof SessionInterface);

        // Already fully authenticated — nothing to challenge.
        if ($session->has(UserInterface::class)) {
            return new RedirectResponse($this->urlHelper->generate('home'));
        }

        if (! TwoFactorSession::isPending($session)) {
            return new RedirectResponse($this->urlHelper->generate('login'));
        }

        if (TwoFactorSession::isExpired($session)) {
            TwoFactorSession::clear($session);

            return new RedirectResponse($this->urlHelper->generate('login') . '?2fa-expired=1');
        }

        if ($request->getMethod() === 'POST') {
            return $this->handleSubmit($request, $session);
        }

        return $this->renderChallenge($request, useRecovery: isset($request->getQueryParams()['recovery']));
    }

    private function handleSubmit(ServerRequestInterface $request, SessionInterface $session): ResponseInterface
    {
        $payload = TwoFactorSession::payload($session);
        $userId  = $this->resolveUserId($payload);
        if ($userId === null) {
            TwoFactorSession::clear($session);

            return new RedirectResponse($this->urlHelper->generate('login'));
        }

        $body         = $request->getParsedBody();
        $body         = is_array($body) ? $body : [];
        $recoveryCode = is_string($body['recovery_code'] ?? null) ? $body['recovery_code'] : '';
        $code         = is_string($body['code'] ?? null) ? $body['code'] : '';
        $useRecovery  = $recoveryCode !== '';

        $verified = $useRecovery
            ? $this->verifyRecovery($userId, $recoveryCode)
            : $this->verifyTotp($userId, $code);

        if (! $verified) {
            $attempts = TwoFactorSession::recordFailedAttempt($session);
            if ($attempts >= TwoFactorSession::MAX_ATTEMPTS) {
                TwoFactorSession::clear($session);

                return new RedirectResponse($this->urlHelper->generate('login') . '?2fa-failed=1');
            }

            return $this->renderChallenge($request, $useRecovery, error: true);
        }

        // Promote the pending user to a full session.
        assert($payload !== null);
        $session->set(UserInterface::class, $payload);
        $session->regenerate();
        TwoFactorSession::clear($session);

        $response = new RedirectResponse($this->urlHelper->generate('home'));

        if (($body['trust_device'] ?? null) !== null) {
            $response = $this->trustDevice($request, $userId, $response);
        }

        return $response;
    }

    private function verifyTotp(UserIdentifier $userId, string $code): bool
    {
        try {
            $twoFactor = $this->userTwoFactorRepository->getByUserId($userId);
        } catch (EntityNotFound) {
            return false;
        }

        if (! $twoFactor->isConfirmed()) {
            return false;
        }

        return $this->twoFactorService->verify(
            $this->twoFactorService->decryptSecret($twoFactor->secret),
            $code,
        );
    }

    private function verifyRecovery(UserIdentifier $userId, string $recoveryCode): bool
    {
        $match = $this->twoFactorService->verifyRecoveryCode(
            $recoveryCode,
            $this->recoveryCodeRepository->getActiveByUserId($userId),
        );

        if ($match === null) {
            return false;
        }

        $this->eventDispatcher->dispatch(new RecoveryCodeConsumed($match->id));

        return true;
    }

    private function trustDevice(
        ServerRequestInterface $request,
        UserIdentifier $userId,
        ResponseInterface $response,
    ): ResponseInterface {
        $days     = $this->twoFactorService->trustedDeviceDays();
        $rawToken = (string) Token::generate(32);

        $userAgent = $request->getHeaderLine('User-Agent');
        $userAgent = $userAgent !== '' ? substr($userAgent, 0, 255) : null;

        $device = new TrustedDevice(
            id: TrustedDeviceIdentifier::create(),
            userId: $userId,
            tokenHash: TrustedDeviceCookie::hash($rawToken),
            expiresAt: TimestampImmutable::fromDateTime(
                (new DateTimeImmutable())->add(new DateInterval(sprintf('P%dD', $days))),
            ),
            createdAt: TimestampImmutable::now(),
            userAgent: $userAgent,
        );

        $this->eventDispatcher->dispatch(new TrustedDeviceAdded($device));

        return TrustedDeviceCookie::withCookie($response, $rawToken, $days);
    }

    /** @param array<array-key, mixed>|null $payload */
    private function resolveUserId(array|null $payload): UserIdentifier|null
    {
        $username = $payload['username'] ?? null;
        if (! is_string($username) || ! UserIdentifier::isValid($username)) {
            return null;
        }

        return UserIdentifier::fromString($username);
    }

    private function renderChallenge(
        ServerRequestInterface $request,
        bool $useRecovery,
        bool $error = false,
    ): ResponseInterface {
        return $this->renderer->render($request, 'page::login/two-factor', [
            'useRecovery'      => $useRecovery,
            'error'            => $error,
            'trustedDeviceDays' => $this->twoFactorService->trustedDeviceDays(),
        ]);
    }
}
