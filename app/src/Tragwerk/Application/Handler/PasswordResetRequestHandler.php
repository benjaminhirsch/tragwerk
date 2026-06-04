<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler;

use DateInterval;
use DateTimeImmutable;
use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\PasswordResetRequest;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\PasswordReset;
use Tragwerk\Domain\Event\PasswordResetRequested;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordResetIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;

use function assert;
use function bin2hex;
use function random_bytes;

final readonly class PasswordResetRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private UserRepository $userRepository,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, PasswordResetRequest::class);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof PasswordResetRequest);

                try {
                    $user          = $this->userRepository->getByEmail($dto->email);
                    $passwordReset = new PasswordReset(
                        id: PasswordResetIdentifier::create(),
                        userId: $user->id,
                        token: bin2hex(random_bytes(32)),
                        expiresAt: TimestampImmutable::fromDateTime(
                            (new DateTimeImmutable())->add(new DateInterval('PT2H')),
                        ),
                        createdAt: TimestampImmutable::now(),
                    );
                    $this->eventDispatcher->dispatch(new PasswordResetRequested($passwordReset, $user));
                } catch (EntityNotFound) {
                    // Silently ignore unknown email addresses for security
                }

                return new RedirectResponse(
                    $this->urlHelper->generate('login') . '?reset-requested=1',
                );
            }
        }

        return $this->renderer->render($request, 'page::password-reset/request', ['validationBag' => $validationBag]);
    }
}
