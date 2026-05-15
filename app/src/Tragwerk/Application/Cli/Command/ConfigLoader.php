<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use DOMDocument;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function file_exists;
use function libxml_clear_errors;
use function libxml_get_errors;
use function sprintf;
use function trim;

use const LIBXML_ERR_FATAL;

#[AsCommand(name: 'tragwerk:config-load', description: 'Loads a given configuration')]
final class ConfigLoader extends Command
{
    public function __invoke(
        #[Argument('Path to the configuration file')]
        string $configFile,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $output->writeln('Loading configuration from: ' . $configFile);

        if (! file_exists($configFile)) {
            $output->writeln('Configuration file not found');

            return self::FAILURE;
        }

        $dom = new DOMDocument();
        $dom->load($configFile);

        if (! $dom->schemaValidate('src/Tragwerk/Domain/Config/schema.xsd')) {
            foreach (libxml_get_errors() as $error) {
                $error->message
                    |> trim(...)
                    |> (static fn ($x) => sprintf(
                        "[%s] Line %d: %s\n",
                        $error->level === LIBXML_ERR_FATAL ? 'FATAL' : 'ERROR',
                        $error->line,
                        $x,
                    ))
                    // @phpstan-ignore-next-line
                    |> $output(...);
            }

            libxml_clear_errors();

            return self::FAILURE;
        }



        return self::SUCCESS;
    }
}
