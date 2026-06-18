<?php

declare(strict_types=1);

namespace Tragwerk\Application\Template\Extension;

use League\Plates\Engine;
use League\Plates\Extension\ExtensionInterface;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function is_array;
use function is_string;

final class EnvironmentContext implements MiddlewareInterface, ExtensionInterface
{
    private string|null $activeEnvironment = null;

    /** @var string[] */
    private array $projectEnvironments = [];

    #[Override]
    public function register(Engine $engine): void
    {
        $engine->registerFunction('activeEnvironment', [$this, 'getActiveEnvironment']);
        $engine->registerFunction('getProjectEnvironments', [$this, 'getProjectEnvironments']);
    }

    public function getActiveEnvironment(): string|null
    {
        return $this->activeEnvironment;
    }

    /** @return string[] */
    public function getProjectEnvironments(): array
    {
        return $this->projectEnvironments;
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $active                  = $request->getAttribute('active_environment');
        $this->activeEnvironment = is_string($active) ? $active : null;
        $environments            = $request->getAttribute('project_environments');
        /** @var string[] $environments */
        $this->projectEnvironments = is_array($environments) ? $environments : [];

        try {
            return $handler->handle($request);
        } finally {
            $this->activeEnvironment   = null;
            $this->projectEnvironments = [];
        }
    }
}
