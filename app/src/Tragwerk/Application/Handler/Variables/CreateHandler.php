<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Variables;

use Fig\Http\Message\RequestMethodInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Helper\UrlHelper;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Dto\Variable\VariableCreation;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Event\EnvVarCreated;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;

use function _;
use function assert;
use function is_string;

final readonly class CreateHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeProject = $request->getAttribute('active_project');
        $activeBranch  = $request->getAttribute('active_environment');

        if (! $activeProject instanceof Project) {
            return new RedirectResponse($this->urlHelper->generate('variable'));
        }

        // Before the first push the repository has no branches, so project scope is the only
        // option the form may offer.
        $activeBranch = is_string($activeBranch) && $activeBranch !== '' ? $activeBranch : null;

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, VariableCreation::class);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof VariableCreation);

                $branch = $dto->isProjectScoped ? null : $activeBranch;

                if ($branch === null && ! $dto->isProjectScoped) {
                    // Environment scope was requested without an environment to attach to.
                    return new RedirectResponse($this->urlHelper->generate('variable'));
                }

                $var = $dto->createEnvVar(EnvVarIdentifier::create(), $activeProject->id, $branch);

                try {
                    $this->eventDispatcher->dispatch(new EnvVarCreated($var));

                    return new RedirectResponse($this->urlHelper->generate('variable'));
                } catch (Throwable) {
                    $validationBag = $validationBag->withError(
                        'key',
                        _('A variable with this key already exists for this environment'),
                    );
                }
            }
        }

        return $this->renderer->render($request, 'page::variable/create', [
            'validationBag'  => $validationBag,
            'canScopeToEnvironment' => $activeBranch !== null,
        ]);
    }
}
