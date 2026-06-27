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
use Tragwerk\Application\Service\ProjectConfigLoader;
use Tragwerk\Application\Service\VolumeSizeReader;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Model\ApplicationConfig;

use function assert;
use function is_string;
use function md5;
use function strlen;
use function substr;

final readonly class MountSizesHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private ProjectConfigLoader $configLoader,
        private VolumeSizeReader $volumeSizeReader,
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
        $app           = $projectConfig?->applications[0] ?? null;

        $services = [];
        if ($projectConfig !== null) {
            $services = $projectConfig->services;
        }

        $hasMounts = $app instanceof ApplicationConfig && $app->mounts !== [];

        $sizes = [];
        $error = null;

        if ($hasMounts || $services !== []) {
            // Cache the SSH `docker system df -v` result briefly. The pool holds a blocking
            // lock per key, so concurrent pollers (multiple tabs/users) wait for a single SSH
            // call instead of each opening their own connection.
            $item = $this->cache->getItem('mount_sizes_' . $project->id->toString() . '_' . md5($branch));

            if ($item->isHit()) {
                /** @var array{sizes: array<string, string>, error: string|null} $result */
                $result = $item->get();
            } else {
                try {
                    $prefix = $this->volumeSizeReader->composeProjectName($project, $branch) . '_';
                    $sizes  = [];

                    foreach ($this->volumeSizeReader->read($project, $branch) as $name => $size) {
                        $sizes[substr($name, strlen($prefix))] = $size;
                    }

                    $result = ['sizes' => $sizes, 'error' => null];
                    $item->set($result)->expiresAfter(55);
                } catch (Throwable $e) {
                    $result = ['sizes' => [], 'error' => $e->getMessage()];
                    $item->set($result)->expiresAfter(10);
                }

                $this->cache->save($item);
            }

            $sizes = $result['sizes'];
            $error = $result['error'];
        }

        return $this->renderer->render($request, 'page::configuration/_mount_sizes', [
            'app'      => $app,
            'services' => $services,
            'sizes'    => $sizes,
            'error'    => $error,
        ]);
    }
}
