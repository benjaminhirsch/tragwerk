<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\User;

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
use Tragwerk\Application\Dto\UpdateLanguage;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\AccountView;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Event\UserLocaleUpdated;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function array_merge;
use function assert;

final readonly class UpdateLanguageHandler implements RequestHandlerInterface
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

        $validationBag = $this->mapper->mapAndValidate($request, UpdateLanguage::class);
        $dto           = $validationBag->getDto();

        if ($validationBag->hasErrors() || ! $dto instanceof UpdateLanguage) {
            return $this->reRender($request, $userId, $validationBag);
        }

        $this->eventDispatcher->dispatch(new UserLocaleUpdated($userId, $dto->locale));

        // Apply immediately for this session; the persisted preference (loaded into
        // the auth details on next login) covers other devices.
        $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
        assert($session instanceof SessionInterface);
        if ($dto->locale !== null) {
            $session->set('locale', $dto->locale->value);
        } else {
            $session->unset('locale');
        }

        return new RedirectResponse($this->urlHelper->generate('account') . '?language-saved=1#language');
    }

    private function reRender(
        ServerRequestInterface $request,
        UserIdentifier $userId,
        ValidationBag $validationBag,
    ): ResponseInterface {
        return $this->renderer->render($request, 'page::account/index', array_merge(
            $this->accountView->build($userId),
            ['languageValidation' => $validationBag],
        ));
    }
}
