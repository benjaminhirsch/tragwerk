<?php

declare(strict_types=1);

namespace Tragwerk\Domain\Config;

use DOMDocument;
use DOMElement;
use DOMText;
use RuntimeException;

use function count;
use function in_array;
use function lcfirst;
use function str_replace;
use function strtolower;
use function trim;
use function ucwords;

final readonly class XmlToArrayConverter
{
    /** @return array<string, mixed> */
    public function convert(DOMDocument $dom): array
    {
        $root = $dom->documentElement;
        if (! $root instanceof DOMElement) {
            throw new RuntimeException('XML document has no root element.');
        }

        return $this->convertElement($root);
    }

    /** @return array<string, mixed> */
    private function convertElement(DOMElement $element): array
    {
        $result = [];

        // Read attributes, kebab-case → camelCase
        foreach ($element->attributes as $attribute) {
            $key          = $this->normalizeKey($attribute->nodeName);
            $result[$key] = $this->castScalar((string) $attribute->nodeValue);
        }

        // Group child elements
        $childGroups = [];
        $textContent = '';

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMText) {
                $textContent .= $child->nodeValue;
                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($this->isPluralWrapper($child->nodeName)) {
                // Transparent wrapper (e.g. <applications>): collect its element
                // children directly into a flat list, keyed by the wrapper name.
                $key  = $this->normalizeKey($child->nodeName);
                $list = [];
                foreach ($child->childNodes as $grandchild) {
                    if (! ($grandchild instanceof DOMElement)) {
                        continue;
                    }

                    $list[] = $this->convertElement($grandchild);
                }

                $result[$key] = $list;
                continue;
            }

            $childGroups[$child->nodeName][] = $this->convertElement($child);
        }

        // CDATA / text content for leaf elements (e.g. hook bodies)
        $trimmedText = trim($textContent);
        if ($trimmedText !== '' && $childGroups === []) {
            $result['value'] = $trimmedText;
        }

        foreach ($childGroups as $name => $items) {
            // List container children get a plural key to match model property names.
            // e.g. <location> children of <web> → 'locations' to match WebConfig::$locations
            $key = $this->isListContainer($name)
                ? $this->normalizeKey($name) . 's'
                : $this->normalizeKey($name);

            // List elements remain lists; single elements are unpacked.
            $result[$key] = count($items) === 1 && ! $this->isListContainer($name)
                ? $items[0]
                : $items;
        }

        return $result;
    }

    /**
     * Plural wrappers are transparent container elements whose only job is grouping
     * their children into a list. Handled inline in the parent loop, not recursed into.
     */
    private function isPluralWrapper(string $name): bool
    {
        return in_array($name, [
            'applications',
            'services',
            'routes',
            'hooks',
            'mounts',
            'relationships',
            'extensions',
            'workers',
        ], true);
    }

    /**
     * List container: elements that should always be treated as a list,
     * even if there is only one entry. Mirrors the XSD maxOccurs="unbounded" elements.
     */
    private function isListContainer(string $name): bool
    {
        return in_array($name, [
            'application',
            'service',
            'route',
            'location',
            'hook',
            'mount',
            'relationship',
        ], true);
    }

    private function normalizeKey(string $name): string
    {
        // kebab-case → camelCase
        return str_replace('-', ' ', $name)
                |> ucwords(...)
                |> (static fn ($x) => str_replace(' ', '', $x))
                |> lcfirst(...);
    }

    private function castScalar(string $value): string|bool
    {
        return match (strtolower($value)) {
            'true'  => true,
            'false' => false,
            default => $value,
        };
    }
}
