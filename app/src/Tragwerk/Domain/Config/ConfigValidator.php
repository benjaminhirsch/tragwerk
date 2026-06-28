<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Config;

use DOMDocument;
use DOMElement;
use LibXMLError;

use function array_map;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function sprintf;
use function trim;

final readonly class ConfigValidator
{
    public function __construct(
        private string $schemaPath,
        private CronScheduleValidator $cronScheduleValidator = new CronScheduleValidator(),
    ) {
    }

    /** @return string[] List of human-readable validation errors, empty if valid */
    public function validate(string $xmlContent): array
    {
        if (trim($xmlContent) === '') {
            return ['Invalid XML: empty content'];
        }

        $doc = new DOMDocument();

        libxml_use_internal_errors(true);

        if (! $doc->loadXML($xmlContent)) {
            $errors = $this->collectErrors();
            libxml_use_internal_errors(false);

            return $errors !== [] ? $errors : ['Invalid XML: could not parse document'];
        }

        $valid = $doc->schemaValidate($this->schemaPath);

        $errors = $this->collectErrors();
        libxml_use_internal_errors(false);

        if (! $valid) {
            return $errors !== [] ? $errors : ['Schema validation failed'];
        }

        // XSD only checks the structural shape of a schedule (field count / descriptor),
        // not whether field values are in range. Validate semantics here so a broken
        // schedule fails the build instead of crash-looping the cron sidecar at runtime.
        return [
            ...$this->validateCronSchedules($doc),
            ...$this->validateLocalPorts($doc),
        ];
    }

    /**
     * Loopback ports bind 127.0.0.1 on the host, so two services sharing a port
     * fail `docker up` with "address already in use". Catch in-config duplicates
     * here; host-wide collisions across projects/branches are not visible to a
     * single config and surface at deploy time.
     *
     * @return string[]
     */
    private function validateLocalPorts(DOMDocument $doc): array
    {
        $errors = [];

        /** @var array<string, string> $seen port => first service name */
        $seen = [];

        foreach ($doc->getElementsByTagName('service') as $service) {
            if (! $service instanceof DOMElement || ! $service->hasAttribute('local-port')) {
                continue;
            }

            $port = $service->getAttribute('local-port');
            $name = $service->getAttribute('name');

            if (isset($seen[$port])) {
                $errors[] = sprintf(
                    'Duplicate local-port "%s" on services "%s" and "%s"',
                    $port,
                    $seen[$port],
                    $name,
                );

                continue;
            }

            $seen[$port] = $name;
        }

        return $errors;
    }

    /** @return string[] */
    private function validateCronSchedules(DOMDocument $doc): array
    {
        $errors = [];

        foreach ($doc->getElementsByTagName('cron') as $cron) {
            if (! $cron instanceof DOMElement) {
                continue;
            }

            $schedule = $cron->getAttribute('schedule');

            if ($this->cronScheduleValidator->isValid($schedule)) {
                continue;
            }

            $errors[] = sprintf(
                'Invalid cron schedule "%s" for cron "%s"',
                $schedule,
                $cron->getAttribute('name'),
            );
        }

        return $errors;
    }

    /** @return string[] */
    private function collectErrors(): array
    {
        return array_map(
            static fn (LibXMLError $e): string => trim($e->message) . ' (line ' . $e->line . ')',
            libxml_get_errors(),
        );
    }
}
