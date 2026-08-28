<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexenergy;

class DatexEnergyStatus
{
    /**
     * @param array<string, mixed> $publication
     * @return list<array{evseId: string, status: string, lastUpdated: string, siteId: string, stationId: string}>
     */
    public static function extractPointStatuses(array $publication): array
    {
        $points = [];
        foreach (DatexEnergySitesBuilder::payloadItems($publication) as $payload) {
            $pub = $payload['aegiEnergyInfrastructureStatusPublication'] ?? null;
            if (!is_array($pub) && isset($payload['energyInfrastructureSiteStatus'])) {
                $pub = $payload;
            }
            if (!is_array($pub)) {
                continue;
            }

            foreach (DatexEnergySitesBuilder::listOfMaps($pub['energyInfrastructureSiteStatus'] ?? []) as $site) {
                $siteId = (string) ($site['reference']['idG'] ?? '');
                foreach (DatexEnergySitesBuilder::listOfMaps($site['energyInfrastructureStationStatus'] ?? []) as $station) {
                    $stationId = (string) ($station['reference']['idG'] ?? '');
                    $lastUpdated = (string) ($station['lastUpdated'] ?? '');
                    foreach (DatexEnergySitesBuilder::listOfMaps($station['refillPointStatus'] ?? []) as $refillPoint) {
                        $electric = $refillPoint['aegiElectricChargingPointStatus'] ?? null;
                        if (!is_array($electric)) {
                            continue;
                        }
                        $evseId = (string) ($electric['reference']['idG'] ?? '');
                        if ($evseId === '') {
                            continue;
                        }
                        $status = (string) ($electric['status']['value'] ?? 'unknown');
                        $points[] = [
                            'evseId' => $evseId,
                            'status' => $status !== '' ? $status : 'unknown',
                            'lastUpdated' => $lastUpdated,
                            'siteId' => $siteId,
                            'stationId' => $stationId,
                        ];
                    }
                }
            }
        }

        return $points;
    }

    /**
     * @param array<string, mixed> $cache
     * @param list<array{evseId: string, status: string, lastUpdated: string, siteId: string, stationId: string}> $points
     * @return array<string, mixed>
     */
    public static function applyPacketToCache(array $cache, array $points, string $packetType, string $lastModified): array
    {
        $byEvse = is_array($cache['points'] ?? null) ? $cache['points'] : [];
        if (strcasecmp($packetType, 'SNAPSHOT') === 0) {
            $byEvse = [];
        }
        foreach ($points as $point) {
            $byEvse[$point['evseId']] = $point;
        }

        $cache['points'] = $byEvse;
        $cache['packetType'] = $packetType;
        $cache['lastModified'] = $lastModified;
        $cache['updatedAt'] = gmdate('c');
        $cache['pointCount'] = count($byEvse);

        return $cache;
    }

    /**
     * @param array<string, mixed> $featureCollection
     * @param array<string, array{evseId: string, status: string, lastUpdated: string, siteId: string, stationId: string}> $statusByEvse
     * @return array{featureCollection: array<string, mixed>, matched: int, unmatched: int}
     */
    public static function mergeStatusIntoFeatureCollection(array $featureCollection, array $statusByEvse): array
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
            $evseIds = self::evseIdsFromProperties($properties);
            if ($evseIds === []) {
                $unmatched++;
                continue;
            }

            $pointStatuses = [];
            foreach ($evseIds as $evseId) {
                if (isset($statusByEvse[$evseId])) {
                    $pointStatuses[] = $statusByEvse[$evseId];
                }
            }
            if ($pointStatuses === []) {
                $unmatched++;
                continue;
            }

            $matched++;
            $features[$index]['properties'] = self::applyStatusProperties($properties, $pointStatuses);
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
     * @return list<string>
     */
    public static function evseIdsFromProperties(array $properties): array
    {
        $ids = $properties['evseIds'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }

        return self::normalizeEvseIds(array_map('strval', $ids));
    }

    /**
     * @param list<string> $ids
     * @return list<string>
     */
    public static function normalizeEvseIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            $id = trim($id);
            if ($id !== '') {
                $normalized[] = $id;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string, mixed> $properties
     * @param list<array{evseId: string, status: string, lastUpdated: string, siteId: string, stationId: string}> $pointStatuses
     * @return array<string, mixed>
     */
    private static function applyStatusProperties(array $properties, array $pointStatuses): array
    {
        $counts = [];
        $latestUpdate = '';
        foreach ($pointStatuses as $point) {
            $status = $point['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            if ($point['lastUpdated'] > $latestUpdate) {
                $latestUpdate = $point['lastUpdated'];
            }
        }

        $properties['evseStatuses'] = $pointStatuses;
        $properties['chargingAvailability'] = self::stationSummary($counts);
        $properties['chargingAvailablePoints'] = $counts['available'] ?? 0;
        $properties['chargingObservedPoints'] = array_sum($counts);
        $properties['chargingStatuses'] = $counts;
        $properties['chargingUpdatedAt'] = $latestUpdate;

        return $properties;
    }

    /**
     * @param array<string, int> $counts
     */
    private static function stationSummary(array $counts): string
    {
        $available = $counts['available'] ?? 0;
        $broken = ($counts['outOfOrder'] ?? 0) + ($counts['inoperative'] ?? 0);
        $busy = ($counts['charging'] ?? 0) + ($counts['occupied'] ?? 0);
        $known = array_sum($counts);
        if ($available === $known && $available > 0) {
            return 'available';
        }
        if ($available > 0) {
            return 'partial';
        }
        if ($broken === $known && $broken > 0) {
            return 'inoperative';
        }
        if ($busy > 0) {
            return 'occupied';
        }

        return 'unknown';
    }
}
