<?php

declare(strict_types=1);

namespace Tragwerk\Factory\Logger;

use Monolog\Handler\FilterHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LogLevel;
use RuntimeException;

use function assert;
use function is_array;
use function is_string;

abstract class LoggerFactory
{
    protected function createLogger(string $name, ContainerInterface $container): Logger
    {
        $config = $container->get('config');
        assert(is_array($config));

        $loggerConfig = $config['loggers'][$name] ?? [];
        assert(is_array($loggerConfig));

        $logger = new Logger($name);

        $logger->pushProcessor(new IntrospectionProcessor());
        $logger->pushProcessor(new ProcessIdProcessor());
        $logger->pushProcessor(new PsrLogMessageProcessor());

        $level = match ($loggerConfig['level'] ?? null) {
            LogLevel::DEBUG => Level::Debug,
            LogLevel::INFO => Level::Info,
            LogLevel::NOTICE => Level::Notice,
            LogLevel::WARNING => Level::Warning,
            LogLevel::ERROR => Level::Error,
            LogLevel::CRITICAL => Level::Critical,
            LogLevel::ALERT => Level::Alert,
            LogLevel::EMERGENCY => Level::Emergency,
            default => throw new RuntimeException('Unknown log level')
        };

        if (( $loggerConfig['infoConsole'] ?? false ) === true) {
            $logger->pushHandler(new FilterHandler(new StreamHandler('php://stdout', $level), [
                Level::Debug,
                Level::Info,
                Level::Notice,
                Level::Warning,
            ]));
        }

        if (( $loggerConfig['errorConsole'] ?? false ) === true) {
            $logger->pushHandler(new FilterHandler(new StreamHandler('php://stderr', $level), [
                Level::Error,
                Level::Critical,
                Level::Alert,
                Level::Emergency,
            ]));
        }

        $infoFile = $loggerConfig['infoFile'] ?? null;
        if (is_string($infoFile) && $infoFile !== '') {
            $logger->pushHandler(new FilterHandler(new StreamHandler($infoFile, $level), [
                Level::Debug,
                Level::Info,
                Level::Notice,
                Level::Warning,
            ]));
        }

        $errorFile = $loggerConfig['errorFile'] ?? null;
        if (is_string($errorFile) && $errorFile !== '') {
            $logger->pushHandler(new FilterHandler(new StreamHandler($errorFile, $level), [
                Level::Error,
                Level::Critical,
                Level::Alert,
                Level::Emergency,
            ]));
        }

        return $logger;
    }
}
