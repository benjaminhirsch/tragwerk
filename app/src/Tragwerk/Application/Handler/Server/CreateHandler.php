<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Server;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Authentication\UserInterface;
use Mezzio\Helper\UrlHelper;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Dto\Server\Server as ServerDto;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Credential;
use Tragwerk\Domain\Entity\SetupJob;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Enum\SetupJobStatus;
use Tragwerk\Domain\Event\ServerCreated;
use Tragwerk\Domain\Event\SetupJobScheduled;
use Tragwerk\Domain\Repository\CredentialRepository;
use Tragwerk\Domain\Repository\ServerRepository;
use Tragwerk\Domain\ValueObject\CredentialIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\SetupJobIdentifier;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function _;
use function assert;

final readonly class CreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private ServerRepository $serverRepository,
        private CredentialRepository $credentialRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeTeam = $request->getAttribute('active_team');
        assert($activeTeam instanceof Team);

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, ServerDto::class);

            $user = $request->getAttribute(UserInterface::class);
            assert($user instanceof UserInterface);

            if (! $validationBag->hasErrors()) {
                $server = $validationBag->getDto();
                assert($server instanceof ServerDto);

                if ($this->serverRepository->existsByHost($server->host)) {
                    $validationBag = $validationBag->withError('host', _('IP address is already in use'));
                } elseif ($server->credentialId !== null && $server->credentialId !== '') {
                    if (! CredentialIdentifier::isValid($server->credentialId)) {
                        $validationBag = $validationBag->withError('credentialId', _('Invalid credential'));
                    } else {
                        try {
                            $credential = $this->credentialRepository->getById(
                                CredentialIdentifier::fromString($server->credentialId),
                            );
                            assert($credential instanceof Credential);

                            if ($credential->teamId->toString() !== $activeTeam->id->toString()) {
                                $validationBag = $validationBag->withError('credentialId', _('Credential not found'));
                            }
                        } catch (Throwable) {
                            $validationBag = $validationBag->withError('credentialId', _('Credential not found'));
                        }
                    }
                }

                if (! $validationBag->hasErrors()) {
                    $serverId = ServerIdentifier::create();

                    $this->eventDispatcher->dispatch(new ServerCreated(
                        $server,
                        UserIdentifier::fromString($user->getIdentity()),
                        $activeTeam->id,
                        $serverId,
                    ));

                    if ($server->credentialId !== null && $server->credentialId !== '') {
                        $now = TimestampImmutable::now();
                        $job = new SetupJob(
                            SetupJobIdentifier::create(),
                            $serverId,
                            SetupJobStatus::Pending,
                            '',
                            $now,
                            $now,
                        );

                        $this->eventDispatcher->dispatch(new SetupJobScheduled($job));

                        return new RedirectResponse($this->urlHelper->generate('server.setup', [
                            'id'    => $serverId->toString(),
                            'jobId' => $job->id->toString(),
                        ]));
                    }

                    return new RedirectResponse($this->urlHelper->generate('server'));
                }
            }
        }

        $credentials = $this->credentialRepository->getAll(teamId: $activeTeam->id);

        return $this->renderer->render($request, 'page::server/create', [
            'validationBag' => $validationBag,
            'credentials'   => $credentials,
        ]);
    }
}
