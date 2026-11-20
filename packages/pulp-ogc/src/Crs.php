<?php

declare(strict_types=1);

namespace OpenMapsight\pulpogc;

/** Compare the CRS spellings that OGC services use for the same EPSG code. */
final class Crs
{
    private const EPSG_PATTERNS = [
        '~^EPSG:(\d+)$~i',
        '~^urn:(?:x-)?ogc:def:crs:EPSG:(?:[^:]*:)?(\d+)$~i',
        '~^https?://www\.opengis\.net/def/crs/EPSG/[^/]+/(\d+)$~i',
        '~^https?://www\.opengis\.net/gml/srs/epsg\.xml#(\d+)$~i',
    ];

    private const CRS84_PATTERNS = [
        '~^OGC:CRS84$~i',
        '~^urn:ogc:def:crs:OGC:[^:]*:CRS84$~i',
        '~^https?://www\.opengis\.net/def/crs/OGC/[^/]+/CRS84$~i',
    ];

    /** `EPSG:<code>` or `OGC:CRS84`, or null for an unrecognized name. */
    public static function normalize(string $name): ?string
    {
        $name = trim($name);
        foreach (self::EPSG_PATTERNS as $pattern) {
            if (preg_match($pattern, $name, $match) === 1) {
                return 'EPSG:' . (int) $match[1];
            }
        }
        foreach (self::CRS84_PATTERNS as $pattern) {
            if (preg_match($pattern, $name) === 1) {
                return 'OGC:CRS84';
            }
        }

        return null;
    }

    /**
     * True when both names identify the same CRS. EPSG:4326 and CRS84 count as the same:
     * they differ only in their formal axis order, which GeoJSON output does not follow.
     * Unrecognized names match only when spelled identically.
     */
    public static function same(string $a, string $b): bool
    {
        $normalA = self::normalize($a);
        $normalB = self::normalize($b);
        if ($normalA === null || $normalB === null) {
            return trim($a) === trim($b);
        }
        $lonLat = ['EPSG:4326', 'OGC:CRS84'];

        return $normalA === $normalB || (in_array($normalA, $lonLat, true) && in_array($normalB, $lonLat, true));
    }

    /** The CRS name of a GeoJSON 2008 `crs` member, or null when absent or not a named CRS. */
    public static function fromGeoJson(array $document): ?string
    {
        $crs = $document['crs'] ?? null;
        if (!is_array($crs)) {
            return null;
        }
        $name = $crs['properties']['name'] ?? null;
        if (($crs['type'] ?? null) === 'name' && is_string($name) && $name !== '') {
            return $name;
        }

        return null;
    }
}
