<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Credential;

use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tragwerk\Application\Response\ResponseRenderer;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Repository\CredentialRepository;

use function iterator_to_array;

final readonly class IndexHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseRenderer $renderer,
        private CredentialRepository $credentialRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $activeTeam = $request->getAttribute('active_team');

        $credentials = $activeTeam instanceof Team
            ? $this->credentialRepository->getAll(teamId: $activeTeam->id)
            : (static function (): Generator {
                yield from [];
            })();

        return $this->renderer->render($request, 'page::credential/index', [
            'credentials' => iterator_to_array(
                $credentials,
            ),
        ]);
    }
}
