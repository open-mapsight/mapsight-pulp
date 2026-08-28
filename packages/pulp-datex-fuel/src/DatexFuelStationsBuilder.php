<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexfuel;

class DatexFuelStationsBuilder
{
    /**
     * @param array{0: float, 1: float, 2: float, 3: float} $bbox
     */
    public function __construct(
        private readonly array $bbox,
        private readonly string $sourceUrl = '',
        private readonly string $sourceName = 'DATEX Fuel',
        private readonly ?string $documentationUrl = null,
        private readonly ?string $publicSourceUrl = null,
    ) {}

    /**
     * @param array<string, mixed>|string $publication
     * @return array<string, mixed>
     */
    public function build(array|string $publication): array
    {
        return $this->collection($this->featuresFromPublication($publication));
    }

    /**
     * @param list<array<string, mixed>> $stations
     * @return array<string, mixed>
     */
    public function buildFromStations(array $stations): array
    {
        return $this->collection($this->featuresFromStations($stations));
    }

    /**
     * @param array<string, mixed>|string $publication
     * @return list<array<string, mixed>>
     */
    public function featuresFromPublication(array|string $publication): array
    {
        return $this->featuresFromStations($this->stationsInBbox($publication));
    }

    /**
     * @param array<string, mixed>|string $publication
     * @return list<array<string, mixed>>
     */
    public function stationsInBbox(array|string $publication): array
    {
        [$minLon, $minLat, $maxLon, $maxLat] = $this->bbox;
        $stations = [];

        foreach (self::stationPublications(DatexFuelXml::publication($publication)) as $station) {
            $coords = self::coordinatesFromStation($station);
            if ($coords === null) {
                continue;
            }
            [$lon, $lat] = $coords;
            if ($lon < $minLon || $lon > $maxLon || $lat < $minLat || $lat > $maxLat) {
                continue;
            }
            $stations[] = $station;
        }

        return $stations;
    }

    /**
     * @param list<array<string, mixed>> $stations
     * @return list<array<string, mixed>>
     */
    public function featuresFromStations(array $stations): array
    {
        $features = [];
        foreach ($stations as $station) {
            $feature = $this->featureFromStation($station);
            if ($feature !== null) {
                $features[] = $feature;
            }
        }

        usort($features, static fn(array $a, array $b): int => strnatcmp(
            (string) ($a['properties']['name'] ?? ''),
            (string) ($b['properties']['name'] ?? '')
        ));

        return $features;
    }

    /**
     * @param array<string, mixed> $station
     * @return array<string, mixed>|null
     */
    public function featureFromStation(array $station): ?array
    {
        $coords = self::coordinatesFromStation($station);
        if ($coords === null) {
            return null;
        }

        $stationId = DatexFuelXml::nodeId($station);
        $name = trim((string) ($station['petrolStationName'] ?? ''));
        $address = $this->addressFromStation($station);
        if ($name === '') {
            $name = $address['line'] !== '' ? $address['line'] : ($stationId !== '' ? $stationId : 'petrol-station');
        }

        $featureId = 'datex-fuel-station-' . self::normalizeId($stationId !== '' ? $stationId : $name);
        $countryCode = trim((string) ($station['petrolStationCountryCode'] ?? $address['countryCode']));

        return [
            'type' => 'Feature',
            'id' => $featureId,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => $coords,
            ],
            'properties' => [
                'id' => $featureId,
                'stationId' => $stationId,
                'name' => $name,
                'brand' => trim((string) ($station['petrolStationBrand'] ?? '')),
                'operator' => trim((string) ($station['petrolStationOperator'] ?? '')),
                'addressLine' => $address['line'],
                'postcode' => $address['postcode'],
                'city' => $address['city'],
                'countryCode' => $countryCode,
                'openingHours' => $this->openingHoursFromStation($station),
                'stationUpdatedAt' => (string) ($station['petrolStationVersionTime'] ?? ''),
                'source' => $this->sourceName,
                'sourceUrl' => $this->publicSourceUrl ?? $this->sourceUrl,
            ],
        ];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    public static function coordinatesFromStation(array $station): ?array
    {
        $location = $station['petrolStationLocation'] ?? null;
        if (!is_array($location) || !isset($location['longitude'], $location['latitude'])) {
            return null;
        }

        $lon = (float) $location['longitude'];
        $lat = (float) $location['latitude'];
        if ($lon === 0.0 && $lat === 0.0) {
            return null;
        }

        return [$lon, $lat];
    }

    /**
     * @return array{line: string, postcode: string, city: string, countryCode: string}
     */
    public function addressFromStation(array $station): array
    {
        $street = trim((string) ($station['petrolStationStreet'] ?? ''));
        $houseNumber = trim((string) ($station['petrolStationHouseNumber'] ?? ''));
        $line = trim($street . ($houseNumber !== '' ? ' ' . $houseNumber : ''));

        return [
            'line' => $line,
            'postcode' => trim((string) ($station['petrolStationPostcode'] ?? '')),
            'city' => trim((string) ($station['petrolStationPlace'] ?? '')),
            'countryCode' => '',
        ];
    }

    /**
     * Structured DATEX / MTS-K opening times: a list of day+period blocks.
     * Each block is `{days: DayEnum[], periods: [{start, end}]}`.
     *
     * @param array<string, mixed> $station
     * @return list<array{days: list<string>, periods: list<array{start: string, end: string}>}>
     */
    public function openingHoursFromStation(array $station): array
    {
        $blocks = [];
        foreach (DatexFuelXml::listOfMaps($station['openingTimes'] ?? []) as $opening) {
            $days = DatexFuelXml::listOfStrings($opening['recurringDayWeekMonthPeriod']['applicableDay'] ?? null);
            $periods = [];
            foreach (DatexFuelXml::listOfMaps($opening['recurringTimePeriodOfDay'] ?? []) as $period) {
                $start = (string) ($period['startTimeOfPeriod'] ?? '');
                $end = (string) ($period['endTimeOfPeriod'] ?? '');
                if ($start !== '' || $end !== '') {
                    $periods[] = ['start' => $start, 'end' => $end];
                }
            }
            if ($days !== [] || $periods !== []) {
                $blocks[] = [
                    'days' => $days,
                    'periods' => $periods,
                ];
            }
        }

        return $blocks;
    }

    /**
     * @param array<string, mixed> $publication
     * @return list<array<string, mixed>>
     */
    public static function stationPublications(array $publication): array
    {
        $stations = [];
        $countryCode = DatexFuelXml::publicationCountryCode($publication);
        foreach (DatexFuelXml::payloadItems($publication) as $payload) {
            foreach (DatexFuelXml::listOfMaps($payload['petrolStation'] ?? []) as $station) {
                if ($countryCode !== '' && !isset($station['petrolStationCountryCode'])) {
                    $station['petrolStationCountryCode'] = $countryCode;
                }
                $stations[] = $station;
            }
        }

        return $stations;
    }

    /**
     * @param list<array<string, mixed>> $features
     * @return array<string, mixed>
     */
    private function collection(array $features): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $features,
            'source' => [
                'name' => $this->sourceName,
                'url' => $this->publicSourceUrl ?? $this->sourceUrl,
                'documentationUrl' => $this->documentationUrl,
                'bbox' => $this->bbox,
            ],
        ];
    }

    private static function normalizeId(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim($id)) ?: 'unknown';
    }
}
