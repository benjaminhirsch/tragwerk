<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Registry;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\RegistryRepository;

use function assert;
use function iterator_to_array;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private RegistryRepository $registryRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeTeam = $request->getAttribute('active_team');
        assert($activeTeam instanceof Team);

        $registries = iterator_to_array($this->registryRepository->getAll($activeTeam->id));

        return $this->renderer->render($request, 'page::registry/index', ['registries' => $registries]);
    }
}
