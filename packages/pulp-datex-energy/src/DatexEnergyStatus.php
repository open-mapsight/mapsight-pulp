<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexenergy;

class DatexEnergyStatus
{
    /**
     * DATEX exchange metadata takes precedence over the optional HTTP Type header.
     * Both snapshotPull and snapshotPush describe full replacements.
     *
     * @param array<string, mixed> $publication
     */
    public static function packetType(array $publication, string $httpType = ''): string
    {
        $protocol = self::exchangeProtocol($publication);
        if (str_starts_with(strtolower($protocol), 'snapshot')) {
            return 'SNAPSHOT';
        }
        if (str_starts_with(strtolower($protocol), 'delta')) {
            return 'DELTA';
        }

        return $httpType !== '' ? $httpType : 'DELTA';
    }

    /** @param array<string, mixed> $node */
    private static function exchangeProtocol(array $node): string
    {
        $protocol = $node['codedExchangeProtocol'] ?? null;
        if (is_string($protocol)) {
            return $protocol;
        }
        if (is_array($protocol) && is_string($protocol['value'] ?? null)) {
            return $protocol['value'];
        }
        foreach ($node as $key => $child) {
            // The payload can be large and is unrelated to exchange metadata.
            if ($key !== 'payload' && is_array($child)) {
                $protocol = self::exchangeProtocol($child);
                if ($protocol !== '') {
                    return $protocol;
                }
            }
        }

        return '';
    }

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
        $previous = is_array($cache['points'] ?? null) ? $cache['points'] : [];
        $byEvse = $previous;
        if (strcasecmp($packetType, 'SNAPSHOT') === 0) {
            $byEvse = [];
        }
        foreach ($points as $point) {
            $existing = $byEvse[$point['evseId']] ?? $previous[$point['evseId']] ?? null;
            $byEvse[$point['evseId']] = is_array($existing) && self::isOlder($point['lastUpdated'], $existing['lastUpdated'])
                ? $existing
                : $point;
        }

        $cache['points'] = $byEvse;
        $cache['packetType'] = $packetType;
        $cache['lastModified'] = $lastModified;
        $cache['updatedAt'] = gmdate('c');
        $cache['pointCount'] = count($byEvse);

        return $cache;
    }

    private static function isOlder(string $incoming, string $existing): bool
    {
        try {
            if ($existing === '') {
                return false;
            }
            $existingTime = new \DateTimeImmutable($existing);
            if ($incoming === '') {
                return true;
            }

            return new \DateTimeImmutable($incoming) < $existingTime;
        } catch (\Exception) {
            // Older callers can supply opaque timestamps; preserve packet order.
            return false;
        }
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
