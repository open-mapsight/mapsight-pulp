<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulpgtfs\GeoJsonHandler;
use OpenMapsight\pulpgtfs\GtfsGeoJsonBuilder;

class PulpGTFS
{
    public static function geoJson(
        string $type,
        string $sourceUrl,
        array $bbox,
        ?string $departuresBaseUrl = null,
        string $sourceName = 'GTFS',
        ?string $documentationUrl = null,
        ?string $publicSourceUrl = null,
        array $options = [],
    ): GeoJsonHandler {
        return new GeoJsonHandler(
            $type,
            $sourceUrl,
            $bbox,
            $departuresBaseUrl,
            $sourceName,
            $documentationUrl,
            $publicSourceUrl,
            $options
        );
    }

    public static function geoJsonBuilder(
        string $sourceUrl,
        array $bbox,
        ?string $departuresBaseUrl = null,
        string $sourceName = 'GTFS',
        ?string $documentationUrl = null,
        ?string $publicSourceUrl = null,
        array $options = [],
    ): GtfsGeoJsonBuilder {
        return new GtfsGeoJsonBuilder(
            $sourceUrl,
            $bbox,
            $departuresBaseUrl,
            $sourceName,
            $documentationUrl,
            $publicSourceUrl,
            (bool)($options['fallbackLineStringsFromStops'] ?? false)
        );
    }
}
