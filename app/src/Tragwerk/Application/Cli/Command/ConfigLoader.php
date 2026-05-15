<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\MapperBuilder;
use DOMDocument;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tragwerk\Domain\Config\XmlToArrayConverter;
use Tragwerk\Domain\Model\ProjectConfig;

use function file_exists;
use function libxml_clear_errors;
use function libxml_get_errors;
use function sprintf;
use function trim;
use function var_dump;

use const LIBXML_ERR_FATAL;

#[AsCommand(name: 'tragwerk:config-load', description: 'Loads a given configuration')]
final class ConfigLoader extends Command
{
    public function __construct(private readonly XmlToArrayConverter $converter)
    {
        parent::__construct();
    }

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

        try {
            $source = $this->converter->convert($dom);
            unset($source['xsi:noNamespaceSchemaLocation']);

            $config = new MapperBuilder()
                ->allowSuperfluousKeys()
                ->allowScalarValueCasting()
                ->mapper()
                ->map(ProjectConfig::class, $source);

            var_dump($config);

            return self::SUCCESS;
        } catch (MappingError $error) {
            foreach ($error->messages() as $message) {
                $output->writeln(
                    '[MAPPING ERROR] path=' . $message->path() .
                    ' type=' . $message->type() . ': ' . $message->toString(),
                );
            }

            return self::FAILURE;
        }
    }
}
