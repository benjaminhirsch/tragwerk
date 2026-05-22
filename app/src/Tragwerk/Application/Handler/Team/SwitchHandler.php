<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Team;

use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionMiddleware;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Middleware\ActiveTeamMiddleware;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Event\UserSwitchedTeam;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function is_array;
use function is_string;

final readonly class SwitchHandler implements RequestHandlerInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body   = $request->getParsedBody();
        $teamId = is_array($body) && is_string($body['teamId'] ?? null) ? $body['teamId'] : null;

        if ($teamId !== null && TeamIdentifier::isValid($teamId)) {
            $raw       = $request->getAttribute('user_teams');
            $userTeams = is_array($raw) ? $raw : [];
            $hasAccess = false;
            foreach ($userTeams as $team) {
                assert($team instanceof Team);
                if ($team->id->toString() === $teamId) {
                    $hasAccess = true;
                    break;
                }
            }

            if ($hasAccess) {
                $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                assert($session instanceof SessionInterface);
                $session->set(ActiveTeamMiddleware::SESSION_KEY, $teamId);

                $user = $request->getAttribute(UserInterface::class);
                assert($user instanceof UserInterface);
                $this->eventDispatcher->dispatch(new UserSwitchedTeam(
                    UserIdentifier::fromString($user->getIdentity()),
                    TeamIdentifier::fromString($teamId),
                ));
            }
        }

        $currentUrl = $request->getHeaderLine('HX-Current-URL')
            ?: $request->getHeaderLine('Referer')
            ?: $this->urlHelper->generate('home');

        if ($request->getHeaderLine('HX-Request') === 'true') {
            return (new HtmlResponse('', 200))->withHeader('HX-Redirect', $currentUrl);
        }

        return new RedirectResponse($currentUrl);
    }
}
