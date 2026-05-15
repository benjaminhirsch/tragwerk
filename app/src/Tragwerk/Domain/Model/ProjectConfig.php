<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Model;

final readonly class ProjectConfig
{
    /**
     * @param list<ApplicationConfig> $applications
     * @param list<ServiceConfig>     $services
     * @param list<RouteConfig>       $routes
     */
    public function __construct(
        public array $applications,
        public array $routes,
        public array $services = [],
    ) {
    }
}
