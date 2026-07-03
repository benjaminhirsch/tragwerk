<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Integration;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Dto\Integration\Integration as IntegrationDto;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\ProjectWebhook;
use Tragwerk\Domain\Enum\GitForge;
use Tragwerk\Domain\Event\WebhookIntegrationCreated;
use Tragwerk\Domain\Repository\ProjectWebhookRepository;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\WebhookIntegrationIdentifier;

use function assert;
use function bin2hex;
use function random_bytes;

final readonly class CreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private ProjectWebhookRepository $webhookRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeProject = $request->getAttribute('active_project');
        if (! $activeProject instanceof Project) {
            return new RedirectResponse($this->urlHelper->generate('integration'));
        }

        // Only one integration may exist per project at a time.
        if ($this->webhookRepository->findByProject($activeProject->id) !== []) {
            return new RedirectResponse($this->urlHelper->generate('integration'));
        }

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, IntegrationDto::class);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof IntegrationDto);

                $integration = new ProjectWebhook(
                    id:          WebhookIntegrationIdentifier::create(),
                    projectId:   $activeProject->id,
                    forge:       $dto->gitForge(),
                    secret:      bin2hex(random_bytes(32)),
                    createdAt:   TimestampImmutable::now(),
                    accessToken: $dto->accessToken(),
                );

                $this->eventDispatcher->dispatch(new WebhookIntegrationCreated($integration));

                return new RedirectResponse($this->urlHelper->generate('integration'));
            }
        }

        return $this->renderer->render($request, 'page::integration/create', [
            'project'       => $activeProject,
            'validationBag' => $validationBag,
            'forges'        => GitForge::cases(),
        ]);
    }
}
