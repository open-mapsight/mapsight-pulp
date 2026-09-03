<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulpdatexroadworks\DatexRoadworksBuilder;
use OpenMapsight\pulpdatexroadworks\GeoJsonHandler;

class PulpDatexRoadworks
{
    /**
     * @param array{0: float, 1: float, 2: float, 3: float} $bbox
     * @param array<string, mixed> $options
     */
    public static function roadworksGeoJson(
        array $bbox,
        string $sourceUrl = '',
        string $sourceName = 'DATEX Roadworks',
        ?string $documentationUrl = null,
        ?string $publicSourceUrl = null,
        array $options = [],
    ): GeoJsonHandler {
        return new GeoJsonHandler(
            $bbox,
            $sourceUrl,
            $sourceName,
            $documentationUrl,
            $publicSourceUrl,
            $options
        );
    }

    /**
     * @param array{0: float, 1: float, 2: float, 3: float} $bbox
     * @param array<string, mixed> $options
     */
    public static function roadworksBuilder(
        array $bbox,
        string $sourceUrl = '',
        string $sourceName = 'DATEX Roadworks',
        ?string $documentationUrl = null,
        ?string $publicSourceUrl = null,
        array $options = [],
    ): DatexRoadworksBuilder {
        return new DatexRoadworksBuilder(
            $bbox,
            $sourceUrl,
            $sourceName,
            $documentationUrl,
            $publicSourceUrl,
            isset($options['feedKind']) ? (string) $options['feedKind'] : 'ald',
        );
    }
}
