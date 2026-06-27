<?php

declare(strict_types=1);

namespace Tragwerk\Application\Handler\Internal;

use Laminas\Diactoros\Response\EmptyResponse;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\SshKey;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\SshKeyRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\SshKeyIdentifier;

use function in_array;
use function is_array;
use function is_string;

/**
 * Internal endpoint queried by the sshd git-auth-wrapper before every git
 * pull/push. Authorizes a given SSH key against the target project via team
 * RBAC. Not reachable from outside the docker network (same trust boundary as
 * the post-receive webhook). Fails closed: anything but an explicit grant is a
 * denial.
 */
final readonly class GitAuthHandler implements RequestHandlerInterface
{
    public function __construct(
        private SshKeyRepository $sshKeyRepository,
        private ProjectRepository $projectRepository,
        private TeamRepository $teamRepository,
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();

        if (! is_array($body)) {
            return new EmptyResponse(400);
        }

        $keyId     = $body['keyId'] ?? null;
        $projectId = $body['projectId'] ?? null;
        $op        = $body['op'] ?? null;

        if (! is_string($keyId) || ! SshKeyIdentifier::isValid($keyId)) {
            return new EmptyResponse(400);
        }

        if (! is_string($projectId) || ! ProjectIdentifier::isValid($projectId)) {
            return new EmptyResponse(400);
        }

        if (! is_string($op) || ! in_array($op, ['read', 'write'], true)) {
            return new EmptyResponse(400);
        }

        $sshKey = $this->sshKeyRepository->getById(SshKeyIdentifier::fromString($keyId));

        if (! $sshKey instanceof SshKey) {
            return new EmptyResponse(403);
        }

        try {
            $project = $this->projectRepository->getById(ProjectIdentifier::fromString($projectId));
        } catch (Throwable) {
            return new EmptyResponse(403);
        }

        if (! $project instanceof Project) {
            return new EmptyResponse(403);
        }

        // Any team membership (Owner/Admin/Member) grants both pull and push.
        $role = $this->teamRepository->roleOf($project->teamId, $sshKey->userId);

        if ($role === null) {
            return new EmptyResponse(403);
        }

        return new EmptyResponse(200);
    }
}
