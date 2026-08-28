<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexfuel;

class DatexFuelPrices
{
    /**
     * Official MTS-K collector examples are full snapshots. SNAPSHOT replaces
     * the cache. If a DELTA packet appears, upsert by station GUID.
     *
     * @param array<string, mixed>|string $publication
     * @return list<array{stationId: string, e5: float|null, e10: float|null, diesel: float|null, updatedAt: string}>
     */
    public static function extractPrices(array|string $publication): array
    {
        $prices = [];
        foreach (DatexFuelXml::payloadItems(DatexFuelXml::publication($publication)) as $payload) {
            foreach (DatexFuelXml::listOfMaps($payload['petrolStationInformation'] ?? []) as $information) {
                $reference = is_array($information['petrolStationReference'] ?? null)
                    ? $information['petrolStationReference']
                    : [];
                $stationId = DatexFuelXml::nodeId($reference);
                if ($stationId === '') {
                    continue;
                }

                $e5 = self::priceFromInformation($information['fuelPriceE5'] ?? null);
                $e10 = self::priceFromInformation($information['fuelPriceE10'] ?? null);
                $diesel = self::priceFromInformation($information['fuelPriceDiesel'] ?? null);
                $updatedAt = self::latestUpdatedAt([
                    self::dateOfPrice($information['fuelPriceE5'] ?? null),
                    self::dateOfPrice($information['fuelPriceE10'] ?? null),
                    self::dateOfPrice($information['fuelPriceDiesel'] ?? null),
                ]);

                $prices[] = [
                    'stationId' => $stationId,
                    'e5' => $e5,
                    'e10' => $e10,
                    'diesel' => $diesel,
                    'updatedAt' => $updatedAt,
                ];
            }
        }

        return $prices;
    }

    /**
     * SNAPSHOT replaces. DELTA upserts by station GUID (hook for feeds that
     * send increments; published MTS-K examples are snapshots).
     *
     * @param array<string, mixed> $cache
     * @param list<array{stationId: string, e5: float|null, e10: float|null, diesel: float|null, updatedAt: string}> $prices
     * @return array<string, mixed>
     */
    public static function applyPricePacketToCache(array $cache, array $prices, string $packetType, string $lastModified): array
    {
        $byStation = is_array($cache['prices'] ?? null) ? $cache['prices'] : [];
        if (strcasecmp($packetType, 'SNAPSHOT') === 0) {
            $byStation = [];
        }
        foreach ($prices as $price) {
            $byStation[$price['stationId']] = $price;
        }

        $cache['prices'] = $byStation;
        $cache['packetType'] = $packetType;
        $cache['lastModified'] = $lastModified;
        $cache['updatedAt'] = gmdate('c');
        $cache['stationCount'] = count($byStation);

        return $cache;
    }

    /**
     * @param array<string, mixed> $featureCollection
     * @param array<string, array{stationId: string, e5: float|null, e10: float|null, diesel: float|null, updatedAt: string}> $pricesByStationId
     * @return array{featureCollection: array<string, mixed>, matched: int, unmatched: int}
     */
    public static function mergePricesIntoFeatureCollection(array $featureCollection, array $pricesByStationId): array
    {
        $features = $featureCollection['features'] ?? [];
        if (!is_array($features)) {
            return ['featureCollection' => $featureCollection, 'matched' => 0, 'unmatched' => 0];
        }

        $matched = 0;
        $unmatched = 0;
        foreach ($features as $index => $feature) {
            if (!is_array($feature)) {
                continue;
            }
            $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $stationId = (string) ($properties['stationId'] ?? '');
            if ($stationId === '' || !isset($pricesByStationId[$stationId])) {
                $unmatched++;
                continue;
            }

            $matched++;
            $features[$index]['properties'] = self::applyPriceProperties($properties, $pricesByStationId[$stationId]);
        }

        $featureCollection['features'] = $features;

        return [
            'featureCollection' => $featureCollection,
            'matched' => $matched,
            'unmatched' => $unmatched,
        ];
    }

    /**
     * @param array<string, mixed> $properties
     * @param array{stationId: string, e5: float|null, e10: float|null, diesel: float|null, updatedAt: string} $price
     * @return array<string, mixed>
     */
    private static function applyPriceProperties(array $properties, array $price): array
    {
        $properties['e5'] = $price['e5'];
        $properties['e10'] = $price['e10'];
        $properties['diesel'] = $price['diesel'];
        $properties['pricesUpdatedAt'] = $price['updatedAt'];

        return $properties;
    }

    private static function priceFromInformation(mixed $information): ?float
    {
        if (!is_array($information) || !isset($information['price'])) {
            return null;
        }
        if (!is_numeric($information['price'])) {
            return null;
        }

        return (float) $information['price'];
    }

    private static function dateOfPrice(mixed $information): string
    {
        if (!is_array($information)) {
            return '';
        }

        return (string) ($information['dateOfPrice'] ?? '');
    }

    /**
     * @param list<string> $timestamps
     */
    private static function latestUpdatedAt(array $timestamps): string
    {
        $latest = '';
        foreach ($timestamps as $timestamp) {
            if ($timestamp !== '' && $timestamp > $latest) {
                $latest = $timestamp;
            }
        }

        return $latest;
    }
}
