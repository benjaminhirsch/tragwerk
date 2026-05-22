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
use Tragwerk\Domain\Entity\Team;

use function is_array;

final class TeamContext implements MiddlewareInterface, ExtensionInterface
{
    private Team|null $activeTeam = null;

    /** @var Team[] */
    private array $userTeams = [];

    #[Override]
    public function register(Engine $engine): void
    {
        $engine->registerFunction('activeTeam', [$this, 'getActiveTeam']);
        $engine->registerFunction('userTeams', [$this, 'getUserTeams']);
    }

    public function getActiveTeam(): Team|null
    {
        return $this->activeTeam;
    }

    /** @return Team[] */
    public function getUserTeams(): array
    {
        return $this->userTeams;
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $active           = $request->getAttribute('active_team');
        $this->activeTeam = $active instanceof Team ? $active : null;
        $teams            = $request->getAttribute('user_teams');
        /** @var Team[] $teams */
        $this->userTeams = is_array($teams) ? $teams : [];

        try {
            return $handler->handle($request);
        } finally {
            $this->activeTeam = null;
            $this->userTeams  = [];
        }
    }
}
