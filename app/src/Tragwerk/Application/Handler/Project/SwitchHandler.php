<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Project;

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
use Tragwerk\Application\Middleware\ActiveProjectMiddleware;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Event\UserSwitchedProject;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
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
        $body      = $request->getParsedBody();
        $projectId = is_array($body) && is_string($body['projectId'] ?? null) ? $body['projectId'] : null;

        if ($projectId !== null && ProjectIdentifier::isValid($projectId)) {
            $raw          = $request->getAttribute('user_projects');
            $userProjects = is_array($raw) ? $raw : [];
            $hasAccess    = false;
            foreach ($userProjects as $project) {
                assert($project instanceof Project);
                if ($project->id->toString() === $projectId) {
                    $hasAccess = true;
                    break;
                }
            }

            if ($hasAccess) {
                $session = $request->getAttribute(SessionMiddleware::SESSION_ATTRIBUTE);
                assert($session instanceof SessionInterface);
                $session->set(ActiveProjectMiddleware::SESSION_KEY, $projectId);

                $user = $request->getAttribute(UserInterface::class);
                assert($user instanceof UserInterface);
                $this->eventDispatcher->dispatch(new UserSwitchedProject(
                    UserIdentifier::fromString($user->getIdentity()),
                    ProjectIdentifier::fromString($projectId),
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
