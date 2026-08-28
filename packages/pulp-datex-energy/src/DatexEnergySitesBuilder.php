<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexenergy;

class DatexEnergySitesBuilder
{
    /**
     * @param array{0: float, 1: float, 2: float, 3: float} $bbox
     */
    public function __construct(
        private readonly array $bbox,
        private readonly string $sourceUrl = '',
        private readonly string $sourceName = 'DATEX Energy',
        private readonly ?string $documentationUrl = null,
        private readonly ?string $publicSourceUrl = null,
        private readonly ?string $preferredLang = null,
    ) {}

    /**
     * @param array<string, mixed> $publication
     * @return array<string, mixed>
     */
    public function build(array $publication): array
    {
        return $this->collection($this->featuresFromPublication($publication));
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @return array<string, mixed>
     */
    public function buildFromSites(array $sites): array
    {
        return $this->collection($this->featuresFromSites($sites));
    }

    /**
     * @param array<string, mixed> $publication
     * @return list<array<string, mixed>>
     */
    public function featuresFromPublication(array $publication): array
    {
        return $this->featuresFromSites($this->sitesInBbox($publication));
    }

    /**
     * @param array<string, mixed> $publication
     * @return list<array<string, mixed>>
     */
    public function sitesInBbox(array $publication): array
    {
        [$minLon, $minLat, $maxLon, $maxLat] = $this->bbox;
        $sites = [];

        foreach (self::tablePublications($publication) as $table) {
            foreach (self::listOfMaps($table['energyInfrastructureSite'] ?? []) as $site) {
                $coords = self::coordinatesFromLocation($site['locationReference'] ?? null);
                if ($coords === null) {
                    continue;
                }
                [$lon, $lat] = $coords;
                if ($lon < $minLon || $lon > $maxLon || $lat < $minLat || $lat > $maxLat) {
                    continue;
                }
                $sites[] = $site;
            }
        }

        return $sites;
    }

    /**
     * @param list<array<string, mixed>> $sites
     * @return list<array<string, mixed>>
     */
    public function featuresFromSites(array $sites): array
    {
        $features = [];
        foreach ($sites as $site) {
            $feature = $this->featureFromSite($site);
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
     * @param array<string, mixed> $site
     * @return array<string, mixed>|null
     */
    public function featureFromSite(array $site): ?array
    {
        $coords = self::coordinatesFromLocation($site['locationReference'] ?? null);
        if ($coords === null) {
            return null;
        }

        $points = $this->chargingPointsFromSite($site);
        if ($points === []) {
            return null;
        }

        $address = $this->addressFromLocation($site['locationReference'] ?? null);
        $siteId = (string) ($site['idG'] ?? '');
        $name = $this->localizedText($site['name'] ?? null);
        if ($name === '') {
            $name = $address['line'] !== '' ? $address['line'] : ($siteId !== '' ? $siteId : 'energy-infrastructure-site');
        }

        $evseIds = DatexEnergyStatus::normalizeEvseIds(array_column($points, 'evseId'));
        $connectorTypes = [];
        $currentTypes = [];
        $maxWatts = 0;
        foreach ($points as $point) {
            foreach ($point['connectorTypes'] as $connectorType) {
                $connectorTypes[$connectorType] = true;
            }
            if ($point['currentType'] !== '') {
                $currentTypes[$point['currentType']] = true;
            }
            $maxWatts = max($maxWatts, $point['watts']);
        }

        $featureId = 'datex-energy-site-' . self::normalizeId($siteId !== '' ? $siteId : $name);

        return [
            'type' => 'Feature',
            'id' => $featureId,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => $coords,
            ],
            'properties' => [
                'id' => $featureId,
                'siteId' => $siteId,
                'name' => $name,
                'operator' => $this->organisationName($site['operator'] ?? null),
                'addressLine' => $address['line'],
                'postcode' => $address['postcode'],
                'city' => $address['city'],
                'countryCode' => $address['countryCode'],
                'evseIds' => $evseIds,
                'chargingPoints' => $points,
                'connectorTypes' => array_keys($connectorTypes),
                'currentTypes' => array_keys($currentTypes),
                'maxPowerWatts' => $maxWatts,
                'lastUpdated' => (string) ($site['lastUpdated'] ?? ''),
                'source' => $this->sourceName,
                'sourceUrl' => $this->publicSourceUrl ?? $this->sourceUrl,
            ],
        ];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    public static function coordinatesFromLocation(mixed $locationReference): ?array
    {
        if (!is_array($locationReference)) {
            return null;
        }

        $display = $locationReference['locAreaLocation']['coordinatesForDisplay']
            ?? $locationReference['locPointLocation']['coordinatesForDisplay']
            ?? null;
        if (!is_array($display) || !isset($display['longitude'], $display['latitude'])) {
            return null;
        }

        $lon = (float) $display['longitude'];
        $lat = (float) $display['latitude'];
        if ($lon === 0.0 && $lat === 0.0) {
            return null;
        }

        return [$lon, $lat];
    }

    /**
     * @return array{line: string, postcode: string, city: string, countryCode: string}
     */
    public function addressFromLocation(mixed $locationReference): array
    {
        $empty = [
            'line' => '',
            'postcode' => '',
            'city' => '',
            'countryCode' => '',
        ];
        if (!is_array($locationReference)) {
            return $empty;
        }

        $address = $locationReference['locAreaLocation']['locLocationExtensionG']['facilityLocation']['address']
            ?? $locationReference['locPointLocation']['locLocationExtensionG']['facilityLocation']['address']
            ?? [];
        if (!is_array($address)) {
            return $empty;
        }

        $line = '';
        foreach (self::listOfMaps($address['addressLine'] ?? []) as $addressLine) {
            $text = $this->localizedText($addressLine['text'] ?? null);
            if ($text !== '') {
                $line = $text;
                break;
            }
        }

        return [
            'line' => $line,
            'postcode' => (string) ($address['postcode'] ?? ''),
            'city' => $this->localizedText($address['city'] ?? null),
            'countryCode' => (string) ($address['countryCode'] ?? ''),
        ];
    }

    public function localizedText(mixed $node): string
    {
        if (!is_array($node)) {
            return is_string($node) ? $node : '';
        }

        $values = $node['values'] ?? null;
        if (!is_array($values)) {
            return '';
        }

        if ($this->preferredLang !== null && $this->preferredLang !== '') {
            foreach ($values as $value) {
                if (!is_array($value)) {
                    continue;
                }
                if (($value['lang'] ?? '') === $this->preferredLang && isset($value['value'])) {
                    return trim((string) $value['value']);
                }
            }
        }

        foreach ($values as $value) {
            if (!is_array($value) || !isset($value['value'])) {
                continue;
            }
            $text = trim((string) $value['value']);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    public function organisationName(mixed $organisation): string
    {
        if (!is_array($organisation)) {
            return '';
        }

        $inner = $organisation['afacAnOrganisation'] ?? $organisation;

        return $this->localizedText(is_array($inner) ? ($inner['name'] ?? null) : null);
    }

    /**
     * @param array<string, mixed> $publication
     * @return list<array<string, mixed>>
     */
    public static function tablePublications(array $publication): array
    {
        $tables = [];
        foreach (self::payloadItems($publication) as $payload) {
            $pub = $payload['aegiEnergyInfrastructureTablePublication'] ?? null;
            if (!is_array($pub) && isset($payload['energyInfrastructureTable'])) {
                $pub = $payload;
            }
            if (!is_array($pub)) {
                continue;
            }
            foreach (self::listOfMaps($pub['energyInfrastructureTable'] ?? []) as $table) {
                $tables[] = $table;
            }
        }

        return $tables;
    }

    /**
     * @param array<string, mixed> $publication
     * @return list<array<string, mixed>>
     */
    public static function payloadItems(array $publication): array
    {
        $messagePayload = $publication['messageContainer']['payload'] ?? null;
        if (is_array($messagePayload)) {
            return self::listOfMaps($messagePayload);
        }

        $payload = $publication['payload'] ?? null;
        if (is_array($payload)) {
            return self::listOfMaps($payload);
        }

        return [$publication];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listOfMaps(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        if ($value === []) {
            return [];
        }
        if (array_is_list($value)) {
            return array_values(array_filter($value, 'is_array'));
        }

        return [$value];
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

    /**
     * @param array<string, mixed> $site
     * @return list<array{evseId: string, stationId: string, watts: int, currentType: string, connectorTypes: list<string>}>
     */
    private function chargingPointsFromSite(array $site): array
    {
        $points = [];
        foreach (self::listOfMaps($site['energyInfrastructureStation'] ?? []) as $station) {
            $stationId = (string) ($station['idG'] ?? '');
            foreach (self::listOfMaps($station['refillPoint'] ?? []) as $refillPoint) {
                $electric = $refillPoint['aegiElectricChargingPoint'] ?? null;
                if (!is_array($electric)) {
                    continue;
                }
                $evseId = trim((string) ($electric['idG'] ?? ''));
                if ($evseId === '') {
                    continue;
                }

                $watts = 0;
                $powers = $electric['availableChargingPower'] ?? [];
                if (is_array($powers)) {
                    foreach ($powers as $power) {
                        if (is_numeric($power)) {
                            $watts = max($watts, (int) $power);
                        }
                    }
                }
                foreach (self::listOfMaps($electric['connector'] ?? []) as $connector) {
                    if (isset($connector['maxPowerAtSocket'])) {
                        $watts = max($watts, (int) $connector['maxPowerAtSocket']);
                    }
                }

                $connectorTypes = [];
                foreach (self::listOfMaps($electric['connector'] ?? []) as $connector) {
                    $type = (string) ($connector['connectorType']['value'] ?? '');
                    if ($type !== '') {
                        $connectorTypes[$type] = true;
                    }
                }

                $points[] = [
                    'evseId' => $evseId,
                    'stationId' => $stationId,
                    'watts' => $watts,
                    'currentType' => (string) ($electric['currentType']['value'] ?? ''),
                    'connectorTypes' => array_keys($connectorTypes),
                ];
            }
        }

        return $points;
    }

    private static function normalizeId(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim($id)) ?: 'unknown';
    }
}
