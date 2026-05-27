<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Profile;

use Fig\Http\Message\RequestMethodInterface;
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
use Tragwerk\Domain\Event\SshKeyCreated;
use Tragwerk\Domain\Event\SshKeyDeleted;
use Tragwerk\Domain\Repository\SshKeyRepository;
use Tragwerk\Domain\ValueObject\SshKeyIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function is_array;
use function is_string;
use function iterator_to_array;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
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

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $action = $this->getPostParam($request, 'action');

            if ($action === 'delete') {
                return $this->handleDelete($request, $userId);
            }

            return $this->handleCreate($request, $userId);
        }

        return $this->renderPage($request, $userId);
    }

    private function handleCreate(ServerRequestInterface $request, UserIdentifier $userId): ResponseInterface
    {
        $validationBag = $this->mapper->mapAndValidate($request, SshKeyCreation::class);

        if (! $validationBag->hasErrors()) {
            $dto = $validationBag->getDto();
            assert($dto instanceof SshKeyCreation);

            $key = $dto->createKey(SshKeyIdentifier::create(), $userId);
            $this->eventDispatcher->dispatch(new SshKeyCreated($key));

            return new RedirectResponse($this->urlHelper->generate('profile'));
        }

        return $this->renderPage($request, $userId, $validationBag);
    }

    private function handleDelete(ServerRequestInterface $request, UserIdentifier $userId): ResponseInterface
    {
        $keyId = $this->getPostParam($request, 'keyId');

        if (is_string($keyId) && SshKeyIdentifier::isValid($keyId)) {
            $keys = iterator_to_array($this->sshKeyRepository->getByUserId($userId), false);

            foreach ($keys as $key) {
                if ($key->id->toString() === $keyId) {
                    $this->eventDispatcher->dispatch(new SshKeyDeleted($key->id));
                    break;
                }
            }
        }

        return new RedirectResponse($this->urlHelper->generate('profile'));
    }

    private function renderPage(
        ServerRequestInterface $request,
        UserIdentifier $userId,
        mixed $validationBag = null,
    ): ResponseInterface {
        $keys = iterator_to_array($this->sshKeyRepository->getByUserId($userId), false);

        return $this->renderer->render($request, 'page::profile/index', [
            'keys'          => $keys,
            'validationBag' => $validationBag,
        ]);
    }

    private function getPostParam(ServerRequestInterface $request, string $name): string|null
    {
        $body = $request->getParsedBody();
        if (! is_array($body)) {
            return null;
        }

        $value = $body[$name] ?? null;

        return is_string($value) ? $value : null;
    }
}
