<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\User;

use Mezzio\Authentication\UserInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\AccountView;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_merge;
use function assert;

final readonly class AccountHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private AccountView $accountView,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);
        $userId = UserIdentifier::fromString($user->getIdentity());

        $queryParams = $request->getQueryParams();

        return $this->renderer->render($request, 'page::account/index', array_merge(
            $this->accountView->build($userId),
            [
                'profileSaved'    => isset($queryParams['profile-saved']),
                'emailPending'    => isset($queryParams['email-pending']),
                'passwordChanged' => isset($queryParams['password-changed']),
                'disableError'    => isset($queryParams['disable-error']),
                'enabled'         => isset($queryParams['enabled']),
            ],
        ));
    }
}
