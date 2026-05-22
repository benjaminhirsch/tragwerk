<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\Team\InviteRegistration;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\TeamInvitationRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;

use function assert;
use function is_string;

final readonly class InviteRegisterHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private TeamInvitationRepository $teamInvitationRepository,
        private UserRepository $userRepository,
        private TeamRepository $teamRepository,
        private UrlHelper $urlHelper,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $token = $request->getAttribute('token');
        assert(is_string($token));

        try {
            $invitation = $this->teamInvitationRepository->getByToken($token);
        } catch (EntityNotFound) {
            return new RedirectResponse($this->urlHelper->generate('login'));
        }

        $validationBag = null;
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, InviteRegistration::class);

            if (! $validationBag->hasErrors()) {
                $registration = $validationBag->getDto();
                assert($registration instanceof InviteRegistration);

                $user = $registration->createUser($invitation->email);
                $this->userRepository->create($user);
                $this->teamRepository->assignUsers($invitation->teamId, [$user->id]);
                $this->teamInvitationRepository->delete($invitation->id);

                return new RedirectResponse($this->urlHelper->generate('login'));
            }
        }

        return $this->renderer->render($request, 'page::team/invite-register', [
            'invitation'    => $invitation,
            'validationBag' => $validationBag,
        ]);
    }
}
