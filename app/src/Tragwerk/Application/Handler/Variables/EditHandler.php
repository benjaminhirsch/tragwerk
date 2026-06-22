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
use Tragwerk\Application\Dto\Variable\VariableUpdate;
use Tragwerk\Application\Mapper\GenericMapper;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Validation\ValidationBag;
use Tragwerk\Domain\Entity\EnvVar;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Event\EnvVarUpdated;
use Tragwerk\Domain\Repository\EnvVarRepository;
use Tragwerk\Domain\ValueObject\EnvVarIdentifier;

use function _;
use function assert;
use function is_string;

final readonly class EditHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private GenericMapper $mapper,
        private EventDispatcherInterface $eventDispatcher,
        private UrlHelper $urlHelper,
        private EnvVarRepository $envVarRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $variable = $this->resolveVariable($request);

        if (! $variable instanceof EnvVar) {
            return new RedirectResponse($this->urlHelper->generate('variable'));
        }

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, VariableUpdate::class);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof VariableUpdate);

                try {
                    $this->eventDispatcher->dispatch(new EnvVarUpdated($dto->applyTo($variable)));

                    return new RedirectResponse($this->urlHelper->generate('variable'));
                } catch (Throwable) {
                    $validationBag = $validationBag->withError(
                        'key',
                        _('A variable with this key already exists for this environment'),
                    );
                }
            }
        }

        if ($validationBag === null) {
            $validationBag = new ValidationBag([
                'key'         => $variable->key,
                'value'       => $variable->isSecret ? '' : $variable->value,
                'isSecret'    => $variable->isSecret ? '1' : '',
                'isInherited' => $variable->isInherited ? '1' : '',
            ]);
        }

        return $this->renderer->render($request, 'page::variable/edit', [
            'variable'      => $variable,
            'validationBag' => $validationBag,
        ]);
    }

    private function resolveVariable(ServerRequestInterface $request): EnvVar|null
    {
        $routeId = $request->getAttribute('id');
        if (! is_string($routeId) || ! EnvVarIdentifier::isValid($routeId)) {
            return null;
        }

        $activeProject = $request->getAttribute('active_project');
        if (! $activeProject instanceof Project) {
            return null;
        }

        try {
            $var = $this->envVarRepository->getById(EnvVarIdentifier::fromString($routeId));

            if ($var->projectId->toString() !== $activeProject->id->toString()) {
                return null;
            }

            return $var;
        } catch (Throwable) {
            return null;
        }
    }
}
