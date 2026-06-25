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
use Tragwerk\Application\Dto\SshKeyCreation;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\AccountView;
use Tragwerk\Domain\Event\SshKeyCreated;
use Tragwerk\Domain\ValueObject\SshKeyIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_merge;
use function assert;

final readonly class AddSshKeyHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private AccountView $accountView,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $user = $request->getAttribute(UserInterface::class);
        assert($user instanceof UserInterface);
        $userId = UserIdentifier::fromString($user->getIdentity());

        $validationBag = $this->mapper->mapAndValidate($request, SshKeyCreation::class);
        $dto           = $validationBag->getDto();

        if ($validationBag->hasErrors() || ! $dto instanceof SshKeyCreation) {
            return $this->renderer->render($request, 'page::account/index', array_merge(
                $this->accountView->build($userId),
                ['sshValidation' => $validationBag],
            ));
        }

        $this->eventDispatcher->dispatch(new SshKeyCreated($dto->createKey(SshKeyIdentifier::create(), $userId)));

        return new RedirectResponse($this->urlHelper->generate('account') . '?ssh-added=1#ssh-keys');
    }
}
