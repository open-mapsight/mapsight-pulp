<?php

declare(strict_types=1);

namespace OpenMapsight\pulpogc;

/**
 * Measure how many decimal places a service delivered.
 *
 * Many servers round output coordinates (GeoServer defaults to a fixed number of
 * decimals). In a geographic CRS, four decimals mean roughly 7 to 11 metres.
 */
final class CoordinatePrecision
{
    private const MAX_DECIMALS = 17;

    /**
     * The largest number of decimal places among all coordinates, or null when the
     * collection has no coordinates. A rounded response never exceeds the rounding.
     */
    public static function decimals(array $collection): ?int
    {
        $max = null;
        foreach ($collection['features'] ?? [] as $feature) {
            if (is_array($feature) && is_array($feature['geometry'] ?? null)) {
                $max = self::geometry($feature['geometry'], $max);
            }
        }

        return $max;
    }

    public static function ofNumber(int|float $value): int
    {
        if (is_int($value) || !is_finite($value)) {
            return 0;
        }
        for ($decimals = 0; $decimals < self::MAX_DECIMALS; $decimals++) {
            if (round($value, $decimals) === $value) {
                return $decimals;
            }
        }

        return self::MAX_DECIMALS;
    }

    private static function geometry(array $geometry, ?int $max): ?int
    {
        if (is_array($geometry['geometries'] ?? null)) {
            foreach ($geometry['geometries'] as $member) {
                if (is_array($member)) {
                    $max = self::geometry($member, $max);
                }
            }
        }
        if (is_array($geometry['coordinates'] ?? null)) {
            $max = self::coordinates($geometry['coordinates'], $max);
        }

        return $max;
    }

    private static function coordinates(array $coordinates, ?int $max): ?int
    {
        foreach ($coordinates as $value) {
            if (is_array($value)) {
                $max = self::coordinates($value, $max);
            } elseif (is_int($value) || is_float($value)) {
                $max = max($max ?? 0, self::ofNumber($value));
            }
        }

        return $max;
    }
}
