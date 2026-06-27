<?php

declare(strict_types=1);

namespace Tragwerk\Application\Cli\Command;

use CuyZ\Valinor\Mapper\MappingError;
use CuyZ\Valinor\MapperBuilder;
use DOMDocument;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use Tragwerk\Domain\Config\XmlToArrayConverter;
use Tragwerk\Domain\Docker\DockerComposeGenerator;
use Tragwerk\Domain\Docker\DockerfileGenerator;
use Tragwerk\Domain\Model\ProjectConfig;

use function chmod;
use function file_exists;
use function file_put_contents;
use function libxml_clear_errors;
use function libxml_get_errors;
use function rtrim;
use function sprintf;
use function trim;

use const LIBXML_ERR_FATAL;

#[AsCommand(name: 'tragwerk:generate-docker', description: 'Generates docker-compose.yml from a project config')]
final class GenerateDockerConfig extends Command
{
    public function __construct(
        private readonly XmlToArrayConverter $converter,
        private readonly DockerComposeGenerator $composeGenerator,
        private readonly DockerfileGenerator $dockerfileGenerator,
    ) {
        parent::__construct();
    }

    public function __invoke(
        #[Argument('Path to the configuration file')]
        string $configFile,
        InputInterface $input,
        OutputInterface $output,
        #[Option(description: 'Output directory for generated files')]
        string $outputDir = '.',
        #[Option(description: 'Email address for Let\'s Encrypt ACME certificate registration')]
        string $acmeEmail = '',
    ): int {
        if (! file_exists($configFile)) {
            $output->writeln('Configuration file not found: ' . $configFile);

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
        } catch (MappingError $error) {
            foreach ($error->messages() as $message) {
                $output->writeln(
                    '[MAPPING ERROR] path=' . $message->path()
                    . ' type=' . $message->type() . ': ' . $message->toString(),
                );
            }

            return self::FAILURE;
        }

        $outDir  = rtrim($outputDir, '/');
        $compose = Yaml::dump($this->composeGenerator->generate($config), 10, 2);

        if (file_put_contents($outDir . '/docker-compose.yml', $compose) === false) {
            $output->writeln('Failed to write docker-compose.yml');

            return self::FAILURE;
        }

        $output->writeln('Generated ' . $outDir . '/docker-compose.yml');

        foreach ($config->applications as $app) {
            $dockerfile = $this->dockerfileGenerator->generate($app);

            $dockerfilePath = $outDir . '/' . $dockerfile->dockerfileName;
            if (file_put_contents($dockerfilePath, $dockerfile->dockerfileContent) === false) {
                $output->writeln('Failed to write ' . $dockerfile->dockerfileName);

                return self::FAILURE;
            }

            $output->writeln('Generated ' . $outDir . '/' . $dockerfile->dockerfileName);

            if ($dockerfile->caddyfileName !== null && $dockerfile->caddyfileContent !== null) {
                $caddyfilePath = $outDir . '/' . $dockerfile->caddyfileName;

                if (file_put_contents($caddyfilePath, $dockerfile->caddyfileContent) === false) {
                    $output->writeln('Failed to write ' . $dockerfile->caddyfileName);

                    return self::FAILURE;
                }

                $output->writeln('Generated ' . $outDir . '/' . $dockerfile->caddyfileName);
            }

            if ($dockerfile->crontabName !== null && $dockerfile->crontabContent !== null) {
                $crontabPath = $outDir . '/' . $dockerfile->crontabName;

                if (file_put_contents($crontabPath, $dockerfile->crontabContent) === false) {
                    $output->writeln('Failed to write ' . $dockerfile->crontabName);

                    return self::FAILURE;
                }

                $output->writeln('Generated ' . $outDir . '/' . $dockerfile->crontabName);
            }

            if ($dockerfile->entrypointName === null || $dockerfile->entrypointContent === null) {
                continue;
            }

            $entrypointPath = $outDir . '/' . $dockerfile->entrypointName;

            if (file_put_contents($entrypointPath, $dockerfile->entrypointContent) === false) {
                $output->writeln('Failed to write ' . $dockerfile->entrypointName);

                return self::FAILURE;
            }

            chmod($entrypointPath, 0755);
            $output->writeln('Generated ' . $outDir . '/' . $dockerfile->entrypointName);
        }

        return self::SUCCESS;
    }
}
