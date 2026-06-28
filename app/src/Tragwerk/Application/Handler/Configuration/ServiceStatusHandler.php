<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Configuration;

use Override;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\ContainerStateReader;
use Tragwerk\Application\Service\ProjectConfigLoader;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Repository\EnvironmentStateRepository;

use function assert;
use function is_string;
use function md5;

final readonly class ServiceStatusHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectConfigLoader $configLoader,
        private ContainerStateReader $containerStateReader,
        private EnvironmentStateRepository $environmentStateRepository,
        private CacheItemPoolInterface $cache,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $request->getAttribute('active_project');
        assert($project instanceof Project);

        $branch = $request->getAttribute('active_environment');
        assert(is_string($branch));

        $projectConfig = $this->configLoader->load($project->id, $branch);

        $services = [];
        if ($projectConfig !== null) {
            $services = $projectConfig->services;
        }

        $states = [];
        $error  = null;

        if ($services !== []) {
            // Cache the SSH result briefly; the pool holds a blocking lock per key so concurrent
            // pollers wait for a single SSH call instead of each opening their own connection.
            $item = $this->cache->getItem('service_states_' . $project->id->toString() . '_' . md5($branch));

            if ($item->isHit()) {
                /** @var array{states: array<string, array{state: string, health: string}>, error: string|null} $result */
                $result = $item->get();
            } else {
                try {
                    $result = ['states' => $this->containerStateReader->read($project, $branch), 'error' => null];
                    $item->set($result)->expiresAfter(15);
                } catch (Throwable $e) {
                    $result = ['states' => [], 'error' => $e->getMessage()];
                    $item->set($result)->expiresAfter(5);
                }

                $this->cache->save($item);
            }

            $states = $result['states'];
            $error  = $result['error'];
        }

        return $this->renderer->render($request, 'page::configuration/_service_status', [
            'services' => $services,
            'states'   => $states,
            'error'    => $error,
            'disabled' => $this->environmentStateRepository->isDisabled($project->id, $branch),
        ]);
    }
}
