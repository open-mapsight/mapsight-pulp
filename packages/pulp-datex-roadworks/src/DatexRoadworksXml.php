<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexroadworks;

use RuntimeException;
use SimpleXMLElement;

class DatexRoadworksXml
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
            throw new RuntimeException('DATEX roadworks publication "' . $fileName . '" is empty');
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

        $payloadPublication = $publication['payloadPublication'] ?? null;
        if (is_array($payloadPublication)) {
            return self::listOfMaps($payloadPublication);
        }

        if (isset($publication['situation'])) {
            return [$publication];
        }

        return [$publication];
    }

    /**
     * @param array<string, mixed> $publication
     * @return list<array<string, mixed>>
     */
    public static function situationRecords(array $publication): array
    {
        $records = [];
        foreach (self::payloadItems($publication) as $payload) {
            foreach (self::listOfMaps($payload['situation'] ?? []) as $situation) {
                $situationId = self::nodeId($situation);
                foreach (self::listOfMaps($situation['situationRecord'] ?? []) as $record) {
                    if ($situationId !== '' && !isset($record['_situationId'])) {
                        $record['_situationId'] = $situationId;
                    }
                    $records[] = $record;
                }
            }
        }

        return $records;
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
     * @param array<string, mixed> $node
     */
    public static function datexType(array $node): string
    {
        foreach (['datexType', 'type', '@type'] as $key) {
            $value = $node[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        $attrs = $node['@attributes'] ?? [];
        if (is_array($attrs)) {
            foreach (['datexType', 'type'] as $key) {
                if (isset($attrs[$key]) && is_string($attrs[$key]) && $attrs[$key] !== '') {
                    return $attrs[$key];
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $node
     */
    public static function multilingualValue(array $node, string $preferredLang = 'de'): string
    {
        $values = $node['values']['value'] ?? $node['value'] ?? null;
        $candidates = [];
        if (is_string($values) && trim($values) !== '') {
            return trim($values);
        }
        foreach (self::listOfMaps($values) as $value) {
            $text = trim((string) ($value['value'] ?? $value['#text'] ?? ''));
            if ($text === '' && isset($value[0]) && is_string($value[0])) {
                $text = trim($value[0]);
            }
            if ($text === '') {
                continue;
            }
            $lang = strtolower((string) ($value['lang'] ?? $value['@attributes']['lang'] ?? ''));
            $candidates[] = ['lang' => $lang, 'text' => $text];
        }
        foreach ($candidates as $candidate) {
            if ($candidate['lang'] === $preferredLang) {
                return $candidate['text'];
            }
        }

        return $candidates[0]['text'] ?? '';
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
            throw new RuntimeException('DATEX roadworks publication "' . $fileName . '" is not valid XML');
        }

        $decoded = self::elementToArray($element);
        if (!is_array($decoded)) {
            throw new RuntimeException('DATEX roadworks publication "' . $fileName . '" must decode to an object');
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
        $xml = (string) preg_replace('/\s+xsi:type="/i', ' datexType="', $xml);
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
