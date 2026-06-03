<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Queue\Handler;

use CuyZ\Valinor\MapperBuilder;
use Generator;
use Mezzio\Authentication\UserInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Tragwerk\Application\Queue\Handler\BuildEnvironment;
use Tragwerk\Application\Queue\Message;
use Tragwerk\Application\Queue\Producer;
use Tragwerk\Application\Queue\Queue;
use Tragwerk\Domain\Config\XmlToArrayConverter;
use Tragwerk\Domain\Docker\DockerComposeGenerator;
use Tragwerk\Domain\Docker\DockerfileGenerator;
use Tragwerk\Domain\Docker\ServiceImageResolver;
use Tragwerk\Domain\Entity\Domain;
use Tragwerk\Domain\Entity\Entity;
use Tragwerk\Domain\Entity\Project;
use Tragwerk\Domain\Entity\SwarmNode;
use Tragwerk\Domain\Entity\Team;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\DomainRepository;
use Tragwerk\Domain\Repository\ProjectRepository;
use Tragwerk\Domain\Repository\TeamRepository;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\DomainIdentifier;
use Tragwerk\Domain\ValueObject\ProjectIdentifier;
use Tragwerk\Domain\ValueObject\ServerIdentifier;
use Tragwerk\Domain\ValueObject\TeamIdentifier;
use Tragwerk\Domain\ValueObject\UserIdentifier;
use Tragwerk\Infrastructure\Git\BareRepository;
use ZipArchive;

use function dirname;
use function exec;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function trim;
use function uniqid;
use function unlink;

final class BuildEnvironmentTest extends TestCase
{
    private string $tempRepoDir;
    private string $tempDataDir;
    private BareRepository $bareRepository;
    private BuildEnvironment $handler;

    private const string MINIMAL_XML = <<<'XML'
        <?xml version="1.0"?>
        <project>
            <applications>
                <application name="app" type="php:8.5" root="/">
                    <web><location path="/" root="public"/></web>
                </application>
            </applications>
            <routes>
                <route pattern="https://{default}" upstream="app:http"/>
            </routes>
        </project>
        XML;

    protected function setUp(): void
    {
        $id                = uniqid();
        $this->tempRepoDir = sys_get_temp_dir() . '/tw-repo-' . $id;
        $this->tempDataDir = sys_get_temp_dir() . '/tw-data-' . $id;

        mkdir($this->tempRepoDir, 0755, true);
        mkdir($this->tempDataDir, 0755, true);

        $this->bareRepository = new BareRepository($this->tempRepoDir, 'http://app');

        $mapper = (new MapperBuilder())
            ->allowSuperfluousKeys()
            ->allowScalarValueCasting()
            ->mapper();

        $nullProducer = new class implements Producer {
            public function sendMessage(
                Message $message,
                Queue $queue = Queue::DEFAULT,
                int|null $priority = 4,
                int|null $delay = null,
                int|null $timeToLive = null,
            ): void {
            }
        };

        $nullDomainRepo = new class implements DomainRepository {
            public function getById(DomainIdentifier $id): Domain
            {
                throw new RuntimeException('not used');
            }

            public function create(Domain $domain): void
            {
            }

            public function delete(DomainIdentifier $id): void
            {
            }

            /** @return list<Domain> */
            public function findByProject(ProjectIdentifier $projectId): array
            {
                return [];
            }

            /** @return list<Domain> */
            public function findByEnvironment(ProjectIdentifier $projectId, string $branch): array
            {
                return [];
            }

            public function clearPrimary(ProjectIdentifier $projectId, string $branch): void
            {
            }

            public function setPrimary(DomainIdentifier $id): void
            {
            }
        };

        $nullProjectRepo = new class implements ProjectRepository {
            public function getById(ProjectIdentifier $id): Entity
            {
                throw new RuntimeException('not used');
            }

            public function create(Project $entity): void
            {
            }

            public function update(Project $entity): void
            {
            }

            public function delete(ProjectIdentifier $id): void
            {
            }

            public function getAll(TeamIdentifier|null $teamId = null): Generator
            {
                yield from [];
            }

            public function isServerInUse(
                ServerIdentifier $serverId,
                ProjectIdentifier|null $excludeProjectId = null,
            ): bool {
                return false;
            }

            public function addSwarmNode(SwarmNode $node): void
            {
            }

            public function removeSwarmNode(ProjectIdentifier $projectId, ServerIdentifier $serverId): void
            {
            }

            /** @return list<SwarmNode> */
            public function getSwarmNodes(ProjectIdentifier $projectId): array
            {
                return [];
            }

            public function getSwarmStorageNode(ProjectIdentifier $projectId): SwarmNode|null
            {
                return null;
            }

            public function swarmNodeExists(ProjectIdentifier $projectId, ServerIdentifier $serverId): bool
            {
                return false;
            }

            public function isServerInSwarmCluster(
                ServerIdentifier $serverId,
                ProjectIdentifier|null $excludeProjectId = null,
            ): bool {
                return false;
            }
        };

        $nullTeamRepo = new class implements TeamRepository {
            public function getById(TeamIdentifier $id): Entity
            {
                throw new RuntimeException('not used');
            }

            public function create(Team $entity): void
            {
            }

            public function update(Team $entity): void
            {
            }

            public function getAll(
                array|null $ids = null,
                array|null $names = null,
                array|null $ownerIds = null,
            ): Generator {
                yield from [];
            }

            public function delete(TeamIdentifier $id): void
            {
            }

            /** @param UserIdentifier[] $userIds */
            public function assignUsers(TeamIdentifier $teamId, array $userIds): void
            {
            }

            public function getByUserId(UserIdentifier $userId): Generator
            {
                yield from [];
            }

            public function getUsersByTeamId(TeamIdentifier $teamId): Generator
            {
                yield from [];
            }

            public function removeUser(TeamIdentifier $teamId, UserIdentifier $userId): void
            {
            }
        };

        $nullUserRepo = new class implements UserRepository {
            public function getById(UserIdentifier $id): Entity
            {
                throw new RuntimeException('not used');
            }

            public function getByEmail(string $email): User
            {
                throw new RuntimeException('not used');
            }

            public function create(User $entity): void
            {
            }

            public function getAll(array|null $ids = null, array|null $emails = null): Generator
            {
                yield from [];
            }

            public function searchByEmail(string $email): Generator
            {
                yield from [];
            }

            public function getLastActiveTeamId(UserIdentifier $userId): TeamIdentifier|null
            {
                return null;
            }

            public function setLastActiveTeam(UserIdentifier $userId, TeamIdentifier $teamId): void
            {
            }

            public function authenticate(string $identity, string|null $credential = null): UserInterface|null
            {
                return null;
            }
        };

        $this->handler = new BuildEnvironment(
            $this->bareRepository,
            new XmlToArrayConverter(),
            $mapper,
            new DockerComposeGenerator(new ServiceImageResolver()),
            new DockerfileGenerator(new ServiceImageResolver()),
            new EventDispatcher(),
            new NullLogger(),
            $this->tempDataDir,
            $nullProducer,
            $nullDomainRepo,
            $nullProjectRepo,
            $nullTeamRepo,
            $nullUserRepo,
            new LockFactory(new InMemoryStore()),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempRepoDir);
        $this->removeDirectory($this->tempDataDir);
    }

    #[Test]
    public function skipsWhenNoConfigFileFound(): void
    {
        $projectId = ProjectIdentifier::create()->toString();
        $this->bareRepository->init($projectId);
        $sha = $this->commitFile($projectId, 'README.md', 'hello');

        $this->handler->handle(new Message\BuildEnvironment($projectId, 'main', $sha));

        self::assertDirectoryDoesNotExist($this->tempDataDir . '/' . $projectId);
    }

    #[Test]
    public function generatesDockerComposeFile(): void
    {
        $projectId = ProjectIdentifier::create()->toString();
        $this->bareRepository->init($projectId);
        $sha = $this->commitFile($projectId, '.tragwerk/config.xml', self::MINIMAL_XML);

        $this->handler->handle(new Message\BuildEnvironment($projectId, 'main', $sha));

        self::assertFileExists($this->tempDataDir . '/' . $projectId . '/main/docker-compose.yml');
    }

    #[Test]
    public function generatesDockerfileForApplication(): void
    {
        $projectId = ProjectIdentifier::create()->toString();
        $this->bareRepository->init($projectId);
        $sha = $this->commitFile($projectId, '.tragwerk/config.xml', self::MINIMAL_XML);

        $this->handler->handle(new Message\BuildEnvironment($projectId, 'main', $sha));

        self::assertFileExists($this->tempDataDir . '/' . $projectId . '/main/Dockerfile.app');
    }

    #[Test]
    public function createsBuildZipFile(): void
    {
        $projectId = ProjectIdentifier::create()->toString();
        $this->bareRepository->init($projectId);
        $sha = $this->commitFile($projectId, '.tragwerk/config.xml', self::MINIMAL_XML);

        $this->handler->handle(new Message\BuildEnvironment($projectId, 'main', $sha));

        self::assertFileExists($this->tempDataDir . '/' . $projectId . '/main/build.zip');
    }

    #[Test]
    public function zipContainsGeneratedDockerFiles(): void
    {
        $projectId = ProjectIdentifier::create()->toString();
        $this->bareRepository->init($projectId);
        $sha = $this->commitFile($projectId, '.tragwerk/config.xml', self::MINIMAL_XML);

        $this->handler->handle(new Message\BuildEnvironment($projectId, 'main', $sha));

        $zip = new ZipArchive();
        $zip->open($this->tempDataDir . '/' . $projectId . '/main/build.zip');

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $names[] = $stat['name'];
        }

        $zip->close();

        self::assertContains('docker-compose.yml', $names);
        self::assertContains('Dockerfile.app', $names);
        self::assertNotContains('build.zip', $names);
    }

    private function commitFile(string $projectId, string $filePath, string $content): string
    {
        $workDir  = sys_get_temp_dir() . '/tw-work-' . uniqid();
        $repoPath = $this->tempRepoDir . '/' . $projectId;

        exec('git clone ' . $repoPath . ' ' . $workDir . ' 2>/dev/null');
        exec('git -C ' . $workDir . " config user.email 'test@test.com' 2>/dev/null");
        exec('git -C ' . $workDir . " config user.name 'Test' 2>/dev/null");

        $fullPath = $workDir . '/' . $filePath;
        $dir      = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($fullPath, $content);

        exec('git -C ' . $workDir . ' add . 2>/dev/null');
        exec('git -C ' . $workDir . " commit -m 'add file' 2>/dev/null");
        exec('git -C ' . $workDir . ' push origin HEAD:main 2>/dev/null');

        $sha = trim((string) exec('git -C ' . $workDir . ' rev-parse HEAD 2>/dev/null'));

        $this->removeDirectory($workDir);

        return $sha;
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;
            if (is_dir($full)) {
                $this->removeDirectory($full);
            } else {
                unlink($full);
            }
        }

        rmdir($path);
    }
}
