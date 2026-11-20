<?php

declare(strict_types=1);

namespace OpenMapsight\pulpogc;

/**
 * Undo UTF-8 text that a service encoded twice.
 *
 * A data store that reads UTF-8 attributes as ISO-8859-1 and then emits UTF-8 turns
 * "ö" into "Ã¶" and "ß" into "Ã" followed by U+009F. The repair is applied only when
 * the whole string converts back losslessly to valid UTF-8, so correct text, ASCII
 * and strings that mix both forms stay unchanged.
 */
final class TextRepair
{
    public static function doubleEncodedUtf8(string $value): string
    {
        // A lead byte (U+00C2–U+00F4) followed by a continuation byte (U+0080–U+00BF),
        // each read as one Latin-1 character.
        if (preg_match('/[\x{00C2}-\x{00F4}][\x{0080}-\x{00BF}]/u', $value) !== 1) {
            return $value;
        }
        if (preg_match('/[^\x{0000}-\x{00FF}]/u', $value) === 1) {
            return $value;
        }
        $bytes = mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
        if (!mb_check_encoding($bytes, 'UTF-8')) {
            return $value;
        }

        return $bytes;
    }

    /** Repair every string value, at any depth, in the properties of each feature. */
    public static function featureProperties(array $collection): array
    {
        foreach ($collection['features'] ?? [] as $index => $feature) {
            if (is_array($feature) && is_array($feature['properties'] ?? null)) {
                $collection['features'][$index]['properties'] = self::values($feature['properties']);
            }
        }

        return $collection;
    }

    private static function values(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $values[$key] = self::doubleEncodedUtf8($value);
            } elseif (is_array($value)) {
                $values[$key] = self::values($value);
            }
        }

        return $values;
    }
}
