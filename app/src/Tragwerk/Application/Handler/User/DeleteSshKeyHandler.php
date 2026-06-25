<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\User;

use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Domain\Event\SshKeyDeleted;
use Tragwerk\Domain\Repository\SshKeyRepository;
use Tragwerk\Domain\ValueObject\SshKeyIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function is_array;
use function is_string;

final readonly class DeleteSshKeyHandler implements RequestHandlerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private SshKeyRepository $sshKeyRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);
        $userId = UserIdentifier::fromString($user->getIdentity());

        $body  = $request->getParsedBody();
        $keyId = is_array($body) && is_string($body['keyId'] ?? null) ? $body['keyId'] : null;

        // Only delete a key that actually belongs to the current user.
        if ($keyId !== null && SshKeyIdentifier::isValid($keyId)) {
            foreach ($this->sshKeyRepository->getByUserId($userId) as $key) {
                if ($key->id->toString() === $keyId) {
                    $this->eventDispatcher->dispatch(new SshKeyDeleted($key->id));
                    break;
                }
            }
        }

        return new RedirectResponse($this->urlHelper->generate('account') . '?ssh-deleted=1#ssh-keys');
    }
}
