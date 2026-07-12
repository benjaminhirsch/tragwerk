<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Event\UserRegistered;
use Tragwerk\Domain\Exception\Repository\EntityNotFound;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function is_string;
use function method_exists;
use function sprintf;

#[AsCommand(name: 'user:create', description: 'Create a new user')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    public function __invoke(
        #[Argument('E-Mail address of the user')]
        string $email,
        #[Argument('Firstname of the user')]
        string $firstname,
        #[Argument('Lastname of the user')]
        string $lastname,
        InputInterface $input,
        OutputInterface $output,
        #[Option('Password. Read from a hidden prompt when omitted, which keeps it out of the shell history.')]
        string $password = '',
        #[Option('Leave the user unconfirmed and mail a confirmation link, like the web registration does.')]
        bool $unconfirmed = false,
    ): int {
        if ($this->emailAlreadyTaken($email)) {
            $output->writeln(sprintf('<error>A user with the email address "%s" already exists.</error>', $email));

            return self::FAILURE;
        }

        if ($password === '') {
            $password = $this->askForPassword($input, $output);
        }

        if ($password === '') {
            $output->writeln('<error>The password must not be empty.</error>');

            return self::FAILURE;
        }

        $now = TimestampImmutable::now();

        // Dispatching UserRegistered is what gives the user their default team
        // (CreateDefaultTeam). Confirming right here — rather than through the
        // mailed token — keeps the command usable on a fresh instance that has
        // no SMTP configured yet, which is how the first admin gets created.
        $this->eventDispatcher->dispatch(
            new UserRegistered(
                new User(
                    UserIdentifier::create(),
                    $email,
                    $firstname,
                    $lastname,
                    PasswordHash::create($password),
                    $now,
                    $now,
                    confirmedAt: $unconfirmed ? null : $now,
                ),
                requiresEmailConfirmation: $unconfirmed,
            ),
        );

        $output->writeln($unconfirmed
            ? sprintf('<info>User "%s" created. A confirmation mail is on its way.</info>', $email)
            : sprintf('<info>User "%s" created and confirmed. You can log in right away.</info>', $email));

        return self::SUCCESS;
    }

    private function askForPassword(InputInterface $input, OutputInterface $output): string
    {
        $helper = $this->getHelper('question');

        $question = new Question('Please enter the password: ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        assert(method_exists($helper, 'ask'));
        $password = $helper->ask($input, $output, $question);

        return is_string($password) ? $password : '';
    }

    private function emailAlreadyTaken(string $email): bool
    {
        try {
            $this->userRepository->getByEmail($email);

            return true;
        } catch (EntityNotFound) {
            return false;
        }
    }
}
