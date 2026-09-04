<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulp\SrcHttpHandler;
use OpenMapsight\pulpdatexroadworks\DatexRoadworksBuilder;
use OpenMapsight\pulpdatexroadworks\GeoJsonHandler;

class PulpDatexRoadworks
{
    public const SUBSCRIPTION_URL = PulpMobilithek::SUBSCRIPTION_URL;

    /**
     * Configures `Pulp::srcHttp` for a Mobilithek subscription GET.
     *
     * Same helper as `PulpMobilithek::srcMobilithek()`. Certificate path,
     * password, and subscription ID stay caller-supplied.
     *
     * @param array<string, mixed> $guzzleOptions
     * @param array<string, mixed> $options
     */
    public static function srcMobilithek(
        string $subscriptionId,
        string $certPath,
        string $certPassword,
        ?string $ifModifiedSince = null,
        string $aliasFileName = 'mobilithek.xml',
        array $guzzleOptions = [],
        array $options = [],
    ): SrcHttpHandler {
        return PulpMobilithek::srcMobilithek(
            $subscriptionId,
            $certPath,
            $certPassword,
            $ifModifiedSince,
            $aliasFileName,
            $guzzleOptions,
            $options
        );
    }

    /**
     * Default Mobilithek Guzzle options: gzip, P12 client cert, subscription query.
     *
     * @param array<string, mixed> $guzzleOptions
     * @return array<string, mixed>
     */
    public static function mobilithekGuzzleOptions(
        string $subscriptionId,
        string $certPath,
        string $certPassword,
        ?string $ifModifiedSince = null,
        array $guzzleOptions = [],
    ): array {
        return PulpMobilithek::mobilithekGuzzleOptions(
            $subscriptionId,
            $certPath,
            $certPassword,
            $ifModifiedSince,
            $guzzleOptions
        );
    }

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
