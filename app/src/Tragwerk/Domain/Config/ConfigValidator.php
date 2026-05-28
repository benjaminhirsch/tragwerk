<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Config;

use DOMDocument;
use LibXMLError;

use function array_map;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function trim;

final readonly class ConfigValidator
{
    public function __construct(
        private string $schemaPath,
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

        return [];
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
