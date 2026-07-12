<?php

declare(strict_types=1);

namespace TragwerkTest\Integration\Application\Cli\Command;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tragwerk\Application\Cli\Command\CreateUserCommand;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Enum\TeamRole;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Infrastructure\Queue\Producer as InfraProducer;
use TragwerkTest\Integration\Support\AppIntegrationTestCase;
use TragwerkTest\Integration\Support\RecordingProducer;

use function assert;
use function is_numeric;

/**
 * Extends the app test case rather than the plain one: --unconfirmed renders the
 * confirmation mail, whose template generates a URL, so the routes have to be
 * registered for it to resolve.
 */
final class CreateUserCommandTest extends AppIntegrationTestCase
{
    private const string EMAIL    = 'ada@example.com';
    private const string PASSWORD = 'secure-password-123';

    private RecordingProducer $producer;
    private CommandTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        // Records the confirmation mail instead of writing it to the queue, so
        // the tests can prove whether one was produced at all. Replaces the
        // NullProducer the parent installs.
        $this->producer = new RecordingProducer();
        $this->container->setAllowOverride(true);
        $this->container->setService(InfraProducer::class, $this->producer);
        $this->container->setAllowOverride(false);

        $command = $this->container->get(CreateUserCommand::class);
        assert($command instanceof Command);

        // Registering with an Application is what materialises the argument and
        // option definitions from the #[Argument]/#[Option] attributes, and it
        // supplies the HelperSet the hidden password prompt needs.
        new Application()->addCommand($command);

        $this->tester = new CommandTester($command);
    }

    #[Test]
    public function createsAConfirmedUserThatCanLogInRightAway(): void
    {
        $exitCode = $this->tester->execute([
            'email'      => self::EMAIL,
            'firstname'  => 'Ada',
            'lastname'   => 'Lovelace',
            '--password' => self::PASSWORD,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNotNull($this->findUser()->confirmedAt);
    }

    #[Test]
    public function createsADefaultTeamOwnedByTheNewUser(): void
    {
        $this->tester->execute([
            'email'      => self::EMAIL,
            'firstname'  => 'Ada',
            'lastname'   => 'Lovelace',
            '--password' => self::PASSWORD,
        ]);

        $memberships = $this->connection->fetchAllAssociative(
            'SELECT role FROM team_users WHERE user_id = :userId',
            ['userId' => $this->findUser()->id->toString()],
        );

        self::assertCount(1, $memberships);
        self::assertSame(TeamRole::Owner->value, $memberships[0]['role']);
    }

    #[Test]
    public function doesNotCreateAnEmailConfirmationOrQueueAMail(): void
    {
        $this->tester->execute([
            'email'      => self::EMAIL,
            'firstname'  => 'Ada',
            'lastname'   => 'Lovelace',
            '--password' => self::PASSWORD,
        ]);

        self::assertSame(0, $this->countEmailConfirmations());
        self::assertSame([], $this->producer->messages);
    }

    #[Test]
    public function unconfirmedLeavesTheUserUnconfirmedAndSendsTheConfirmationMail(): void
    {
        $exitCode = $this->tester->execute([
            'email'         => self::EMAIL,
            'firstname'     => 'Ada',
            'lastname'      => 'Lovelace',
            '--password'    => self::PASSWORD,
            '--unconfirmed' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertNull($this->findUser()->confirmedAt);
        self::assertSame(1, $this->countEmailConfirmations());
        self::assertCount(1, $this->producer->messages);
    }

    #[Test]
    public function readsThePasswordFromAHiddenPromptWhenTheOptionIsOmitted(): void
    {
        $this->tester->setInputs([self::PASSWORD]);

        $exitCode = $this->tester->execute([
            'email'     => self::EMAIL,
            'firstname' => 'Ada',
            'lastname'  => 'Lovelace',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertTrue($this->findUser()->password->verify(self::PASSWORD));
    }

    #[Test]
    public function rejectsAnEmailAddressThatIsAlreadyTaken(): void
    {
        $arguments = [
            'email'      => self::EMAIL,
            'firstname'  => 'Ada',
            'lastname'   => 'Lovelace',
            '--password' => self::PASSWORD,
        ];

        $this->tester->execute($arguments);
        $exitCode = $this->tester->execute($arguments);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('already exists', $this->tester->getDisplay());
        self::assertSame(1, $this->countUsers());
    }

    private function findUser(): User
    {
        $repository = $this->container->get(UserRepository::class);
        assert($repository instanceof UserRepository);

        return $repository->getByEmail(self::EMAIL);
    }

    private function countUsers(): int
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM users WHERE email = :email',
            ['email' => self::EMAIL],
        );
        assert(is_numeric($count));

        return (int) $count;
    }

    private function countEmailConfirmations(): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM email_confirmations');
        assert(is_numeric($count));

        return (int) $count;
    }
}
