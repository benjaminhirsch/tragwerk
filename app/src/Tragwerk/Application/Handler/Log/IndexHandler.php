<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Log;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Application\Service\ProjectConfigLoader;
use Tragwerk\Domain\Entity\Project;

use function assert;
use function is_string;
use function preg_replace;
use function strtolower;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectConfigLoader $configLoader,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $project = $request->getAttribute('active_project');
        assert($project instanceof Project);

        $branch = $request->getAttribute('active_environment');
        assert(is_string($branch));

        $services = $this->services($project, $branch);

        return $this->renderer->render($request, 'page::log/index', [
            'project'         => $project,
            'branch'          => $branch,
            'services'        => $services,
            'selectedService' => $services[0]['value'] ?? '',
        ]);
    }

    /**
     * Enumerates the selectable container services for the environment from config.xml (no SSH):
     * the app itself, its background workers and its cron sidecar.
     *
     * @return list<array{value: string, label: string}>
     */
    private function services(Project $project, string $branch): array
    {
        $config = $this->configLoader->load($project->id, $branch);
        if ($config === null) {
            return [];
        }

        $services = [];
        foreach ($config->applications as $app) {
            $appSlug    = $this->slugify($app->name);
            $services[] = ['value' => $appSlug, 'label' => 'App: ' . $app->name];

            foreach ($app->workers as $worker) {
                $services[] = [
                    'value' => $appSlug . '-worker-' . $this->slugify($worker->name),
                    'label' => 'Worker: ' . $worker->name,
                ];
            }

            if ($app->crons === []) {
                continue;
            }

            $services[] = ['value' => $appSlug . '-cron', 'label' => 'Cron: ' . $app->name];
        }

        return $services;
    }

    private function slugify(string $name): string
    {
        $slug = preg_replace('/[\s_]+/', '-', strtolower($name)) ?? '';

        return preg_replace('/[^a-z0-9-]/', '', $slug) ?? '';
    }
}
