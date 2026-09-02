<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulp\SrcHttpHandler;
use OpenMapsight\pulpdatexfuel\AccumulatePricesHandler;
use OpenMapsight\pulpdatexfuel\DatexFuelPrices;
use OpenMapsight\pulpdatexfuel\DatexFuelStationsBuilder;
use OpenMapsight\pulpdatexfuel\GeoJsonHandler;
use OpenMapsight\pulpdatexfuel\MergePricesHandler;
use OpenMapsight\pulpdatexfuel\MobilithekRequest;
use OpenMapsight\pulpdatexfuel\PricesHandler;

class PulpDatexFuel
{
    public const SUBSCRIPTION_URL = MobilithekRequest::SUBSCRIPTION_URL;

    /**
     * Configures `Pulp::srcHttp` for a Mobilithek subscription GET.
     *
     * Certificate path and password stay caller-supplied. Subscription IDs are
     * not packaged. `Pulp::srcHttp` still loads the response body as a string;
     * a nationwide dump may need a disk sink in core pulp later.
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
        return MobilithekRequest::srcHttp(
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
        return MobilithekRequest::guzzleOptions(
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
    public static function stationsGeoJson(
        array $bbox,
        string $sourceUrl = '',
        string $sourceName = 'DATEX Fuel',
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
    public static function stationsBuilder(
        array $bbox,
        string $sourceUrl = '',
        string $sourceName = 'DATEX Fuel',
        ?string $documentationUrl = null,
        ?string $publicSourceUrl = null,
        array $options = [],
    ): DatexFuelStationsBuilder {
        return new DatexFuelStationsBuilder(
            $bbox,
            $sourceUrl,
            $sourceName,
            $documentationUrl,
            $publicSourceUrl
        );
    }

    public static function priceRecords(): PricesHandler
    {
        return new PricesHandler();
    }

    /**
     * SNAPSHOT/DELTA accumulator that reads and writes `$cachePath`.
     * HTTP 304/204 files leave the cache as-is; 200 files apply the packet.
     */
    public static function accumulatePrices(string $cachePath): AccumulatePricesHandler
    {
        return new AccumulatePricesHandler($cachePath);
    }

    /**
     * @param array<string, array{stationId: string, e5: float|null, e10: float|null, diesel: float|null, updatedAt: string}> $pricesByStationId
     */
    public static function mergePrices(array $pricesByStationId): MergePricesHandler
    {
        return new MergePricesHandler($pricesByStationId);
    }

    /**
     * @param array<string, mixed>|string $publication
     * @return list<array{stationId: string, e5: float|null, e10: float|null, diesel: float|null, updatedAt: string}>
     */
    public static function extractPrices(array|string $publication): array
    {
        return DatexFuelPrices::extractPrices($publication);
    }

    /**
     * @param array<string, mixed> $cache
     * @param list<array{stationId: string, e5: float|null, e10: float|null, diesel: float|null, updatedAt: string}> $prices
     * @return array<string, mixed>
     */
    public static function applyPricePacketToCache(array $cache, array $prices, string $packetType, string $lastModified): array
    {
        return DatexFuelPrices::applyPricePacketToCache($cache, $prices, $packetType, $lastModified);
    }

    /**
     * @param array<string, mixed> $featureCollection
     * @param array<string, array{stationId: string, e5: float|null, e10: float|null, diesel: float|null, updatedAt: string}> $pricesByStationId
     * @return array{featureCollection: array<string, mixed>, matched: int, unmatched: int}
     */
    public static function mergePricesIntoFeatureCollection(array $featureCollection, array $pricesByStationId): array
    {
        return DatexFuelPrices::mergePricesIntoFeatureCollection($featureCollection, $pricesByStationId);
    }
}
