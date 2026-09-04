<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulp\SrcHttpHandler;
use OpenMapsight\pulpdatexenergy\AccumulateStatusHandler;
use OpenMapsight\pulpdatexenergy\DatexEnergySitesBuilder;
use OpenMapsight\pulpdatexenergy\DatexEnergyStatus;
use OpenMapsight\pulpdatexenergy\GeoJsonHandler;
use OpenMapsight\pulpdatexenergy\MergeStatusHandler;
use OpenMapsight\pulpdatexenergy\StatusHandler;

class PulpDatexEnergy
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
        string $aliasFileName = 'mobilithek.json',
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
    public static function sitesGeoJson(
        array $bbox,
        string $sourceUrl = '',
        string $sourceName = 'DATEX Energy',
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
    public static function sitesBuilder(
        array $bbox,
        string $sourceUrl = '',
        string $sourceName = 'DATEX Energy',
        ?string $documentationUrl = null,
        ?string $publicSourceUrl = null,
        array $options = [],
    ): DatexEnergySitesBuilder {
        return new DatexEnergySitesBuilder(
            $bbox,
            $sourceUrl,
            $sourceName,
            $documentationUrl,
            $publicSourceUrl,
            isset($options['preferredLang']) ? (string) $options['preferredLang'] : null
        );
    }

    public static function statusRecords(): StatusHandler
    {
        return new StatusHandler();
    }

    /**
     * SNAPSHOT/DELTA accumulator that reads and writes `$cachePath`.
     * HTTP 304/204 files leave the cache as-is; 200 files apply the packet.
     */
    public static function accumulateStatus(string $cachePath): AccumulateStatusHandler
    {
        return new AccumulateStatusHandler($cachePath);
    }

    /**
     * @param array<string, array{evseId: string, status: string, lastUpdated: string, siteId: string, stationId: string}> $statusByEvse
     */
    public static function mergeStatus(array $statusByEvse): MergeStatusHandler
    {
        return new MergeStatusHandler($statusByEvse);
    }

    /**
     * @param array<string, mixed> $publication
     * @return list<array{evseId: string, status: string, lastUpdated: string, siteId: string, stationId: string}>
     */
    public static function extractPointStatuses(array $publication): array
    {
        return DatexEnergyStatus::extractPointStatuses($publication);
    }

    /**
     * @param array<string, mixed> $cache
     * @param list<array{evseId: string, status: string, lastUpdated: string, siteId: string, stationId: string}> $points
     * @return array<string, mixed>
     */
    public static function applyPacketToCache(array $cache, array $points, string $packetType, string $lastModified): array
    {
        return DatexEnergyStatus::applyPacketToCache($cache, $points, $packetType, $lastModified);
    }

    /**
     * @param array<string, mixed> $featureCollection
     * @param array<string, array{evseId: string, status: string, lastUpdated: string, siteId: string, stationId: string}> $statusByEvse
     * @return array{featureCollection: array<string, mixed>, matched: int, unmatched: int}
     */
    public static function mergeStatusIntoFeatureCollection(array $featureCollection, array $statusByEvse): array
    {
        return DatexEnergyStatus::mergeStatusIntoFeatureCollection($featureCollection, $statusByEvse);
    }
}
