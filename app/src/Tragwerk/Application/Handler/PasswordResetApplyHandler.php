<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\PasswordResetApply;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\PasswordResetRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;

use function assert;
use function is_string;

final readonly class PasswordResetApplyHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private PasswordResetRepository $passwordResetRepository,
        private UserRepository $userRepository,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $token = $request->getAttribute('token');
        assert(is_string($token));
/*
        try {
            $passwordReset = $this->passwordResetRepository->getByToken($token);
        } catch (EntityNotFound) {
            return $this->renderer->render($request, 'page::password-reset/invalid');
        }

        if ($passwordReset->expiresAt->isPast() || $passwordReset->usedAt !== null) {
            return $this->renderer->render($request, 'page::password-reset/invalid');
        }*/

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, PasswordResetApply::class);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof PasswordResetApply);

                /*$this->userRepository->updatePassword(
                    $passwordReset->userId,
                    (string) PasswordHash::create($dto->password1),
                );
                $this->passwordResetRepository->markUsed($passwordReset->id);

                return new RedirectResponse(
                    $this->urlHelper->generate('login') . '?password-reset=1',
                );*/
            }
        }

        return $this->renderer->render($request, 'page::password-reset/apply', [
            'token'         => $token,
            'validationBag' => $validationBag,
        ]);
    }
}
