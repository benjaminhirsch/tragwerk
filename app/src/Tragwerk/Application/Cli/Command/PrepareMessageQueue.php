<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use Enqueue\Dbal\DbalContext;
use Interop\Queue\Context;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'worker:prepare-queue', description: 'Setup/configure the message broker')]
final class PrepareMessageQueue extends Command
{
    public function __construct(
        private Context $context,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'test',
            't',
            InputOption::VALUE_OPTIONAL,
            'Run the worker in test mode, listening for message produced during tests',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->context instanceof DbalContext) {
            $this->context->createDataBaseTable();
        }

        return self::SUCCESS;
    }
}
