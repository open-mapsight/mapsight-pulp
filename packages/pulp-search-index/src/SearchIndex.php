<?php

declare(strict_types=1);

namespace OpenMapsight\pulpsearchindex;

use Collator;
use InvalidArgumentException;
use Normalizer;
use stdClass;

/**
 * Search rows of GeoJSON features, and the ranking over them.
 *
 * A row is `[id, title, subtitle, words, numbers, point]`: the feature id, a
 * plain-text title and subtitle, the unique normalized words of its text
 * properties, the keys of its place numbers (e.g. market stands), and its
 * first coordinate. Clients that build rows or rank themselves must follow
 * the same rules; `test/fixtures` pins them.
 *
 * Documents may be decoded as arrays or as objects. JSON objects are read
 * the way JavaScript enumerates them: integer-like keys first, ascending.
 */
final class SearchIndex
{
    public const TEXT_PROPERTIES = [
        'name', 'title', 'listName', 'listInformation', 'description', 'tooltip',
        'category', 'categories', 'additionalCategories', 'tags', 'tagGroups',
        'searchTerms', 'keywords', 'address', 'street', 'city', 'angebot',
        'number', 'standNumber', 'standnummer', 'standNummer', 'stand_nr',
    ];
    public const NUMBER_PROPERTIES = ['number', 'standNumber', 'standnummer', 'standNummer', 'stand_nr'];
    private const ENTITIES = [
        'amp' => '&', 'lt' => '<', 'gt' => '>', 'quot' => '"', 'apos' => "'", 'nbsp' => ' ',
        'auml' => 'ä', 'ouml' => 'ö', 'uuml' => 'ü', 'Auml' => 'Ä', 'Ouml' => 'Ö', 'Uuml' => 'Ü',
        'szlig' => 'ß', 'eacute' => 'é', 'egrave' => 'è', 'acute' => '´', 'ndash' => '–',
        'mdash' => '—', 'hellip' => '…', 'laquo' => '«', 'raquo' => '»', 'bull' => '•',
        'copy' => '©', 'reg' => '®', 'euro' => '€', 'deg' => '°', 'middot' => '·',
        'bdquo' => '„', 'ldquo' => '“', 'rdquo' => '”', 'sbquo' => '‚', 'lsquo' => '‘', 'rsquo' => '’',
    ];
    /** JavaScript's `\s`, which PCRE does not match without UCP. */
    private const WHITESPACE = '[\t\n\x{0B}\f\r \x{A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]';

    /**
     * Rows of a FeatureCollection: one per feature with a usable id, the
     * first feature of each id only.
     *
     * @param array<string, mixed>|stdClass $document
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: list<string>, 5: array{0: float|int, 1: float|int}|null}>
     */
    public static function rows(array|stdClass $document): array
    {
        $features = self::get($document, 'features');
        if (!is_array($features) || !array_is_list($features)) {
            throw new InvalidArgumentException('Expected a GeoJSON FeatureCollection');
        }
        $seen = [];
        $rows = [];
        foreach ($features as $feature) {
            if (!self::isObject($feature) || self::get($feature, 'type') !== 'Feature'
                || !self::has($feature, 'id') || !self::has($feature, 'geometry') || !self::has($feature, 'properties')) {
                continue;
            }
            $id = self::get($feature, 'id');
            $id = is_string($id) ? $id : (is_int($id) || is_float($id) ? self::numberText($id) : '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $rows[] = self::row($id, self::get($feature, 'properties'), self::get($feature, 'geometry'));
        }

        return $rows;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: list<string>, 5: array{0: float|int, 1: float|int}|null}
     */
    private static function row(string $id, mixed $properties, mixed $geometry): array
    {
        $prop = static fn(string $key): mixed => self::isObject($properties) ? self::get($properties, $key) : null;

        $numbers = [];
        foreach (self::NUMBER_PROPERTIES as $key) {
            array_push($numbers, ...self::textValues($prop($key)));
        }
        if (preg_match('/^stand[-_ ]?(\d+[a-z]?)$/iD', $id, $match) === 1) {
            $numbers[] = $match[1];
        }
        $number = $numbers[0] ?? '';

        $title = '';
        foreach (['name', 'title', 'listName'] as $key) {
            $title = self::plainText($prop($key));
            if ($title !== '') {
                break;
            }
        }
        if ($title === '') {
            $title = $number !== '' ? 'Stand ' . $number : $id;
        }

        $category = implode(', ', self::textValues($prop('category') ?? $prop('categories')));
        $parts = array_filter(
            [$number !== '' ? 'Stand ' . $number : '', $category !== '' ? $category : self::plainText($prop('listInformation'))],
            static fn(string $part): bool => $part !== '',
        );
        $subtitle = mb_substr(implode(' · ', $parts), 0, 180, 'UTF-8');

        $text = [$title, $id];
        foreach (self::TEXT_PROPERTIES as $key) {
            array_push($text, ...self::textValues($prop($key)));
        }
        foreach ($numbers as $value) {
            array_push($text, $value, 'Stand ' . $value);
        }
        $words = array_values(array_unique(array_filter(
            explode(' ', self::normalize(implode(' ', $text))),
            static fn(string $word): bool => $word !== '',
        )));

        $keys = [];
        foreach ($numbers as $value) {
            $key = self::numberKey(self::normalize($value));
            if ($key !== '') {
                $keys[$key] = true;
            }
        }

        return [$id, $title, $subtitle, implode(' ', $words), array_map('strval', array_keys($keys)), self::firstPoint($geometry)];
    }

    /**
     * Places for a query, ranked by source priority, exact place number,
     * exact name, name prefix, all words in the name, title (German, numbers
     * by value), then source and id. Every query word must occur in a row's
     * words or in a title of its source; number words must match a place
     * number or a whole word. The same place in several sources (same id,
     * name and first coordinate) appears once.
     *
     * @param array<string, array{titles?: list<string>, rows: list<array>}> $sources Sources by key
     * @param list<array{0: int, 1: string}> $requested [priority, key], lower priority first
     * @return array{places: list<array{0: string, 1: string, 2: string, 3: string, 4: array|null, 5: int}>, missing: list<string>}
     *               Places as [key, id, title, subtitle, point, score]; requested keys without a source
     */
    public static function search(array $sources, array $requested, string $query, int $limit = 40): array
    {
        $normalized = self::normalize($query);
        $tokens = self::tokens($query);
        $queryNumber = self::numberKey($normalized);
        $matches = [];
        $missing = [];
        $visited = [];
        foreach ($requested as [$priority, $path]) {
            if (isset($visited[$path])) {
                continue;
            }
            $visited[$path] = true;
            if (!isset($sources[$path])) {
                $missing[] = $path;
                continue;
            }
            if ($tokens === []) {
                continue;
            }
            $sourceText = self::normalize(implode(' ', $sources[$path]['titles'] ?? []));
            foreach ($sources[$path]['rows'] as [$id, $title, $subtitle, $words, $numbers, $point]) {
                $text = ' ' . $words . ' ';
                $numberSet = array_flip($numbers);
                foreach ($tokens as $token) {
                    $found = ctype_digit($token)
                        ? isset($numberSet[self::numberKey($token)]) || str_contains($text, ' ' . $token . ' ')
                        : str_contains($text, $token) || str_contains($sourceText, $token);
                    if (!$found) {
                        continue 2;
                    }
                }
                $name = self::normalize($title);
                $inName = array_filter($tokens, static fn(string $token): bool => str_contains($name, $token));
                $score = isset($numberSet[$queryNumber]) ? 0 : ($name === $normalized ? 1
                    : (str_starts_with($name, $normalized) ? 2 : (count($inName) === count($tokens) ? 3 : 4)));
                $matches[] = [
                    'priority' => $priority, 'score' => $score, 'title' => $title,
                    'key' => json_encode([$path, $id], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'identity' => json_encode([$id, $name, $point]),
                    'row' => [$path, $id, $title, $subtitle, $point, $score],
                ];
            }
        }

        $collator = new Collator('de');
        $collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
        usort($matches, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority'] ?: $a['score'] <=> $b['score']
            ?: $collator->compare($a['title'], $b['title']) ?: strcmp($a['key'], $b['key']));
        $places = [];
        $seen = [];
        foreach ($matches as $match) {
            if (isset($seen[$match['identity']])) {
                continue;
            }
            $seen[$match['identity']] = true;
            $places[] = $match['row'];
            if (count($places) === $limit) {
                break;
            }
        }

        return ['places' => $places, 'missing' => $missing];
    }

    /**
     * The words of a query, as `search()` matches them.
     *
     * @return list<string>
     */
    public static function tokens(string $query): array
    {
        $normalized = self::normalize($query);

        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    /** Leading zeroes of a place number do not count. */
    public static function numberKey(string $value): string
    {
        return preg_replace('/^0+(?=\d)/', '', $value) ?? $value;
    }

    /** Text only: markup never reaches the index, script and style content never matches. */
    public static function plainText(mixed $value): string
    {
        if (is_string($value)) {
            $text = $value;
        } elseif (is_int($value) || is_float($value)) {
            $text = self::numberText($value);
        } else {
            return '';
        }
        $text = preg_replace('/<(script|style)\b[^>]*>[\s\S]*?<\/\1' . self::WHITESPACE . '*>/iu', ' ', $text) ?? '';
        $text = preg_replace('/<[^>]*>/u', ' ', $text) ?? '';
        $text = preg_replace_callback('/&(#x[\da-f]+|#\d+|[a-z]+);/iu', static function (array $match): string {
            $code = $match[1];
            if ($code[0] !== '#') {
                return self::ENTITIES[$code] ?? $match[0];
            }
            $point = strtolower($code[1]) === 'x' ? hexdec(substr($code, 2)) : (float) substr($code, 1);
            if ($point > 0 && $point <= 0x10FFFF && !($point >= 0xD800 && $point <= 0xDFFF)) {
                $char = mb_chr((int) $point, 'UTF-8');

                return $char === false ? ' ' : $char;
            }

            return ' ';
        }, $text) ?? '';
        $text = preg_replace('/' . self::WHITESPACE . '+/u', ' ', $text) ?? '';

        return trim($text, ' ');
    }

    /** Lower case without accents; German umlauts, their ASCII spellings and ß compare equally. */
    public static function normalize(mixed $value): string
    {
        $text = mb_strtolower(self::plainText($value), 'UTF-8');
        $decomposed = Normalizer::normalize($text, Normalizer::FORM_KD);
        $text = preg_replace('/\p{M}/u', '', is_string($decomposed) ? $decomposed : $text) ?? '';
        $text = str_replace(['ß', 'ae', 'oe', 'ue'], ['ss', 'a', 'o', 'u'], $text);
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '';

        return trim($text, ' ');
    }

    /** `String(number)` as JavaScript prints JSON numbers. */
    private static function numberText(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if ($value == 0.0) {
            return '0';
        }
        if (floor($value) === $value && abs($value) < 1e15) {
            return sprintf('%.0f', $value);
        }

        return (string) json_encode($value);
    }

    /**
     * @return list<string>
     */
    private static function textValues(mixed $value, int $depth = 0): array
    {
        if ($depth > 5 || $value === null) {
            return [];
        }
        if (is_string($value) || is_int($value) || is_float($value)) {
            return [self::plainText($value)];
        }
        $values = [];
        if (is_array($value) && array_is_list($value)) {
            foreach ($value as $item) {
                array_push($values, ...self::textValues($item, $depth + 1));
            }
        } elseif (self::isObject($value)) {
            foreach (self::entries($value) as [$key, $item]) {
                $values[] = self::plainText($key);
                array_push($values, ...self::textValues($item, $depth + 1));
            }
        }

        return $values;
    }

    /**
     * @return array{0: float|int, 1: float|int}|null
     */
    private static function firstPoint(mixed $geometry, int $depth = 0): ?array
    {
        if (!self::isObject($geometry) || $depth > 5) {
            return null;
        }
        $parts = self::get($geometry, 'geometries');
        if (is_array($parts)) {
            foreach ($parts as $part) {
                $point = self::firstPoint($part, $depth + 1);
                if ($point !== null) {
                    return $point;
                }
            }

            return null;
        }
        $coordinates = self::get($geometry, 'coordinates');
        while (is_array($coordinates) && is_array($coordinates[0] ?? null)) {
            $coordinates = $coordinates[0];
        }
        if (!is_array($coordinates)) {
            return null;
        }
        $x = $coordinates[0] ?? null;
        $y = $coordinates[1] ?? null;
        $finite = static fn(mixed $n): bool => (is_int($n) || is_float($n)) && is_finite((float) $n);

        return $finite($x) && $finite($y) ? [$x, $y] : null;
    }

    /** A JSON object, decoded as an object or as an associative array. */
    private static function isObject(mixed $value): bool
    {
        return $value instanceof stdClass || (is_array($value) && ($value === [] || !array_is_list($value)));
    }

    private static function has(array|stdClass $object, string $key): bool
    {
        return $object instanceof stdClass ? property_exists($object, $key) : array_key_exists($key, $object);
    }

    private static function get(mixed $object, string $key): mixed
    {
        return $object instanceof stdClass ? ($object->{$key} ?? null) : (is_array($object) ? ($object[$key] ?? null) : null);
    }

    /**
     * Keys and values in JavaScript's order: integer-like keys ascending,
     * then the others as inserted.
     *
     * @return list<array{0: string, 1: mixed}>
     */
    private static function entries(array|stdClass $object): array
    {
        $indices = [];
        $others = [];
        foreach ($object instanceof stdClass ? get_object_vars($object) : $object as $key => $value) {
            $key = (string) $key;
            if (preg_match('/^(0|[1-9]\d{0,9})$/D', $key) === 1 && (int) $key < 4294967295) {
                $indices[(int) $key] = [$key, $value];
            } else {
                $others[] = [$key, $value];
            }
        }
        ksort($indices);

        return [...array_values($indices), ...$others];
    }
}
