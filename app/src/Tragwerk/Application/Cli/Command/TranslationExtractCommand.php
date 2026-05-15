<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use function assert;
use function explode;
use function is_string;
use function shell_exec;
use function trim;

#[AsCommand(name: 'translation:extract', description: 'Extract translations from strings')]
final class TranslationExtractCommand extends Command
{
    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $findCommand = shell_exec('find src/ templates/ -type f \( -name "*.php" -o -name "*.phtml" \)');
        assert(is_string($findCommand));
        $process = new Process([
            'xgettext',
            '--language=PHP',
            '--output=data/translations/messages.po',
            '--from-code=UTF-8',
            '--keyword=_',
            '--keyword=__',
            '--keyword=translate',
            '--keyword=translatePlural:1,2',
            '--keyword=t',
            '--keyword=tp:1,2',
            '--no-location',
            '--omit-header',
            '--sort-by-file',
            ...explode("\n", trim($findCommand)),
        ]);

        $process->setTimeout(300);
        $process->run(static function ($type, $buffer) use ($output): void {
            $output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        shell_exec('rm -rf data/temp');

        return self::SUCCESS;
    }
}
