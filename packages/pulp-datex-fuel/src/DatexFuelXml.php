<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexfuel;

use RuntimeException;
use SimpleXMLElement;

class DatexFuelXml
{
    /**
     * @return array<string, mixed>
     */
    public static function publication(array|string $content, string $fileName = 'publication'): array
    {
        if (is_array($content)) {
            return self::unwrapContainers($content);
        }
        if ($content === '') {
            throw new RuntimeException('DATEX Fuel publication "' . $fileName . '" is empty');
        }

        return self::unwrapContainers(self::decodeXml($content, $fileName));
    }

    /**
     * @param array<string, mixed> $publication
     * @return list<array<string, mixed>>
     */
    public static function payloadItems(array $publication): array
    {
        $publication = self::unwrapContainers($publication);

        $messagePayload = $publication['messageContainer']['payload'] ?? null;
        if (is_array($messagePayload)) {
            return self::listOfMaps($messagePayload);
        }

        $payload = $publication['payload'] ?? null;
        if (is_array($payload) && !isset($publication['payloadPublication']) && !isset($publication['petrolStation']) && !isset($publication['petrolStationInformation'])) {
            return self::listOfMaps($payload);
        }

        $payloadPublication = $publication['payloadPublication'] ?? null;
        if (is_array($payloadPublication)) {
            return self::listOfMaps($payloadPublication);
        }

        return [$publication];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listOfMaps(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        if ($value === []) {
            return [];
        }
        if (array_is_list($value)) {
            return array_values(array_filter($value, 'is_array'));
        }

        return [$value];
    }

    /**
     * @return list<string>
     */
    public static function listOfStrings(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_array($value)) {
            return [(string) $value];
        }
        if ($value === []) {
            return [];
        }
        if (!array_is_list($value)) {
            return [(string) reset($value)];
        }

        $strings = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $strings[] = (string) $item;
            }
        }

        return $strings;
    }

    /**
     * @param array<string, mixed> $node
     */
    public static function nodeId(array $node): string
    {
        if (isset($node['id']) && (is_string($node['id']) || is_numeric($node['id']))) {
            return (string) $node['id'];
        }

        $attrs = $node['@attributes'] ?? [];
        if (is_array($attrs) && isset($attrs['id'])) {
            return (string) $attrs['id'];
        }

        return (string) ($node['@id'] ?? '');
    }

    /**
     * @param array<string, mixed> $publication
     */
    public static function publicationCountryCode(array $publication): string
    {
        foreach (self::payloadItems($publication) as $payload) {
            $country = $payload['publicationCreator']['country'] ?? null;
            if (is_string($country) && $country !== '') {
                return strtoupper($country);
            }
        }

        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function decodeXml(string $xml, string $fileName = 'publication'): array
    {
        $previous = libxml_use_internal_errors(true);
        $element = simplexml_load_string(self::stripNamespaces($xml), SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$element instanceof SimpleXMLElement) {
            throw new RuntimeException('DATEX Fuel publication "' . $fileName . '" is not valid XML');
        }

        $decoded = self::elementToArray($element);
        if (!is_array($decoded)) {
            throw new RuntimeException('DATEX Fuel publication "' . $fileName . '" must decode to an object');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $publication
     * @return array<string, mixed>
     */
    public static function unwrapContainers(array $publication): array
    {
        foreach (['Envelope', 'Body', 'd2LogicalModel', 'putDatex2Data', 'getDatex2Data', 'getDatex2DataResponse'] as $key) {
            $inner = $publication[$key] ?? null;
            if (is_array($inner)) {
                return self::unwrapContainers($inner);
            }
        }

        return $publication;
    }

    public static function stripNamespaces(string $xml): string
    {
        $xml = (string) preg_replace('/xmlns(?::\w+)?="[^"]*"/i', '', $xml);
        $xml = (string) preg_replace('/(<\/*)[\w.-]+:/', '$1', $xml);
        $xml = (string) preg_replace('/\s+xsi:[\w.-]+="[^"]*"/i', '', $xml);

        return $xml;
    }

    private static function elementToArray(SimpleXMLElement $element): array|string
    {
        $attributes = [];
        foreach ($element->attributes() as $name => $value) {
            $attributes[(string) $name] = (string) $value;
        }

        $children = [];
        foreach ($element->children() as $child) {
            $name = $child->getName();
            $value = self::elementToArray($child);
            if (!array_key_exists($name, $children)) {
                $children[$name] = $value;
                continue;
            }
            if (!is_array($children[$name]) || !array_is_list($children[$name])) {
                $children[$name] = [$children[$name]];
            }
            $children[$name][] = $value;
        }

        $text = trim((string) $element);
        if ($children === [] && $attributes === []) {
            return $text;
        }

        $node = $attributes + $children;
        if ($text !== '' && $children === []) {
            $node['value'] = $text;
        }

        return $node;
    }
}
