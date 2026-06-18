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

        if (! $activeProject instanceof Project || ! is_string($activeBranch) || $activeBranch === '') {
            return new RedirectResponse($this->urlHelper->generate('variable'));
        }

        $validationBag = null;

        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {
            $validationBag = $this->mapper->mapAndValidate($request, VariableCreation::class);

            if (! $validationBag->hasErrors()) {
                $dto = $validationBag->getDto();
                assert($dto instanceof VariableCreation);

                $var = $dto->createEnvVar(EnvVarIdentifier::create(), $activeProject->id, $activeBranch);

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

        return $this->renderer->render($request, 'page::variable/create', ['validationBag' => $validationBag]);
    }
}
