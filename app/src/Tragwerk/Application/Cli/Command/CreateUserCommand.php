<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Tragwerk\Domain\Entity\User;
use Tragwerk\Domain\Repository\UserRepository;
use Tragwerk\Domain\ValueObject\PasswordHash;
use Tragwerk\Domain\ValueObject\TimestampImmutable;
use Tragwerk\Domain\ValueObject\UserIdentifier;

use function assert;
use function is_string;
use function method_exists;

#[AsCommand(name: 'user:create', description: 'Create a new user')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
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
        #[Argument('Displayname of the user')]
        string $displayname,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $helper = $this->getHelper('question');

        $question = new Question('Bitte geben Sie das Passwort ein: ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        assert(method_exists($helper, 'ask'));
        $password = $helper->ask($input, $output, $question);
        assert(is_string($password));
        $now = TimestampImmutable::now();

        $this->userRepository->create(
            new User(
                UserIdentifier::create(),
                $email,
                $firstname,
                $lastname,
                $displayname,
                PasswordHash::create($password),
                $now,
                $now,
            ),
        );

        return self::SUCCESS;
    }
}
