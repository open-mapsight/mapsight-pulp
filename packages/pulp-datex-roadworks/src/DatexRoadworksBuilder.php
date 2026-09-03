<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexroadworks;

class DatexRoadworksBuilder
{
    /**
     * @param array{0: float, 1: float, 2: float, 3: float} $bbox
     */
    public function __construct(
        private readonly array $bbox,
        private readonly string $sourceUrl = '',
        private readonly string $sourceName = 'DATEX Roadworks',
        private readonly ?string $documentationUrl = null,
        private readonly ?string $publicSourceUrl = null,
        private readonly string $feedKind = 'ald',
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
     * @param list<array<string, mixed>> $records
     * @return array<string, mixed>
     */
    public function buildFromRecords(array $records): array
    {
        return $this->collection($this->featuresFromRecords($records));
    }

    /**
     * @param array<string, mixed>|string $publication
     * @return list<array<string, mixed>>
     */
    public function featuresFromPublication(array|string $publication): array
    {
        return $this->featuresFromRecords($this->recordsInBbox($publication));
    }

    /**
     * @param array<string, mixed>|string $publication
     * @return list<array<string, mixed>>
     */
    public function recordsInBbox(array|string $publication): array
    {
        $records = [];
        foreach (DatexRoadworksXml::situationRecords(DatexRoadworksXml::publication($publication)) as $record) {
            if ($this->coordinatesFromRecord($record) === null && $this->lineFromRecord($record) === []) {
                continue;
            }
            if (!$this->recordIntersectsBbox($record)) {
                continue;
            }
            $records[] = $record;
        }

        return $records;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return list<array<string, mixed>>
     */
    public function featuresFromRecords(array $records): array
    {
        $features = [];
        foreach ($records as $record) {
            $feature = $this->featureFromRecord($record);
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
     * @param array<string, mixed> $record
     * @return array<string, mixed>|null
     */
    public function featureFromRecord(array $record): ?array
    {
        $point = $this->coordinatesFromRecord($record);
        $line = $this->lineFromRecord($record);
        if ($point === null && $line === []) {
            return null;
        }

        $recordId = DatexRoadworksXml::nodeId($record);
        $situationId = (string) ($record['_situationId'] ?? '');
        $stableId = $recordId !== '' ? $recordId : ($situationId !== '' ? $situationId : ($point[0] ?? 0) . ',' . ($point[1] ?? 0));
        $featureId = 'datex-roadworks-' . $this->feedKind . '-' . self::normalizeId((string) $stableId);
        $comment = $this->commentFromRecord($record);
        $locationName = $this->locationNameFromRecord($record);
        $name = $locationName !== '' ? $locationName : ($comment !== '' ? self::firstLine($comment) : $featureId);
        $validity = $this->validityFromRecord($record);
        $recordType = DatexRoadworksXml::datexType($record);

        $geometry = $this->geometry($point, $line);
        if ($geometry === null) {
            return null;
        }

        return [
            'type' => 'Feature',
            'id' => $featureId,
            'geometry' => $geometry,
            'when' => [
                'start' => $validity['start'],
                'end' => $validity['end'],
            ],
            'properties' => [
                'id' => $featureId,
                'recordId' => $recordId,
                'situationId' => $situationId,
                'name' => $name,
                'comment' => $comment,
                'locationName' => $locationName,
                'recordType' => $recordType,
                'feedKind' => $this->feedKind,
                'roadMaintenanceType' => $this->firstScalar($record['roadMaintenanceType'] ?? null),
                'roadworksDuration' => $this->firstScalar($record['roadworksDuration'] ?? null),
                'underTraffic' => $this->nullableBool($record['underTraffic'] ?? null),
                'probabilityOfOccurrence' => $this->firstScalar($record['probabilityOfOccurrence'] ?? null),
                'start' => $validity['start'],
                'end' => $validity['end'],
                'updatedAt' => $this->firstScalar($record['situationRecordVersionTime'] ?? $record['situationRecordCreationTime'] ?? null),
                'source' => $this->sourceName,
                'sourceUrl' => $this->publicSourceUrl ?? $this->sourceUrl,
                'documentationUrl' => $this->documentationUrl,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return array{0: float, 1: float}|null
     */
    public function coordinatesFromRecord(array $record): ?array
    {
        $display = $this->firstNodeByKey($record, 'locationForDisplay');
        $coords = $this->latLonPair($display);
        if ($coords !== null) {
            return $coords;
        }

        $pointCoordinates = $this->firstNodeByKey($record, 'pointCoordinates');
        $coords = $this->latLonPair($pointCoordinates);
        if ($coords !== null) {
            return $coords;
        }

        $line = $this->lineFromRecord($record);

        return $line[0] ?? null;
    }

    /**
     * @param array<string, mixed> $record
     * @return list<array{0: float, 1: float}>
     */
    public function lineFromRecord(array $record): array
    {
        $points = [];
        $this->collectLatLon($record, $points);
        $unique = [];
        $seen = [];
        foreach ($points as $point) {
            $key = number_format($point[0], 6, '.', '') . ',' . number_format($point[1], 6, '.', '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $point;
        }

        return count($unique) >= 2 ? $unique : [];
    }

    /**
     * @param array<string, mixed> $record
     */
    public function recordIntersectsBbox(array $record): bool
    {
        $point = $this->coordinatesFromRecord($record);
        if ($point !== null && $this->pointInBbox($point[0], $point[1])) {
            return true;
        }
        foreach ($this->lineFromRecord($record) as $coord) {
            if ($this->pointInBbox($coord[0], $coord[1])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $record
     */
    public function commentFromRecord(array $record): string
    {
        foreach (DatexRoadworksXml::listOfMaps($record['generalPublicComment'] ?? []) as $comment) {
            $text = DatexRoadworksXml::multilingualValue(is_array($comment['comment'] ?? null) ? $comment['comment'] : $comment);
            if ($text !== '') {
                return $text;
            }
        }
        foreach (['nonGeneralPublicComment', 'comment'] as $key) {
            foreach (DatexRoadworksXml::listOfMaps($record[$key] ?? []) as $comment) {
                $text = DatexRoadworksXml::multilingualValue(is_array($comment['comment'] ?? null) ? $comment['comment'] : $comment);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $record
     */
    public function locationNameFromRecord(array $record): string
    {
        foreach (['locationName', 'locationDescriptor', 'predefinedLocationName'] as $key) {
            $node = $this->firstNodeByKey($record, $key);
            if ($node !== null) {
                $text = DatexRoadworksXml::multilingualValue($node);
                if ($text !== '') {
                    return $text;
                }
            }
        }
        $linear = $this->firstNodeByKey($record, 'linearElement');
        if ($linear !== null) {
            $road = $this->firstScalar($linear['roadNumber'] ?? null);
            if ($road !== '') {
                return $road;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $record
     * @return array{start: string|null, end: string|null}
     */
    public function validityFromRecord(array $record): array
    {
        $spec = $record['validity']['validityTimeSpecification'] ?? $record['validity'] ?? [];
        if (!is_array($spec)) {
            $spec = [];
        }

        return [
            'start' => $this->isoOrNull($spec['overallStartTime'] ?? null),
            'end' => $this->isoOrNull($spec['overallEndTime'] ?? null),
        ];
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
     * @param array{0: float, 1: float}|null $point
     * @param list<array{0: float, 1: float}> $line
     * @return array<string, mixed>|null
     */
    private function geometry(?array $point, array $line): ?array
    {
        if ($line !== [] && $point !== null) {
            return [
                'type' => 'GeometryCollection',
                'geometries' => [
                    ['type' => 'Point', 'coordinates' => $point],
                    ['type' => 'LineString', 'coordinates' => $line],
                ],
            ];
        }
        if ($line !== []) {
            return [
                'type' => 'LineString',
                'coordinates' => $line,
            ];
        }
        if ($point !== null) {
            return [
                'type' => 'Point',
                'coordinates' => $point,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $node
     * @param list<array{0: float, 1: float}> $points
     */
    private function collectLatLon(array $node, array &$points): void
    {
        $pair = $this->latLonPair($node);
        if ($pair !== null) {
            $points[] = $pair;
        }
        foreach ($this->gmlPairsFromNode($node) as $gmlPair) {
            $points[] = $gmlPair;
        }
        foreach ($node as $key => $value) {
            if ($key === '@attributes' || !is_array($value)) {
                continue;
            }
            if (array_is_list($value)) {
                foreach ($value as $child) {
                    if (is_array($child)) {
                        $this->collectLatLon($child, $points);
                    }
                }
                continue;
            }
            $this->collectLatLon($value, $points);
        }
    }

    /**
     * @param array<string, mixed> $node
     * @return list<array{0: float, 1: float}>
     */
    private function gmlPairsFromNode(array $node): array
    {
        $srsName = $this->firstScalar($node['srsName'] ?? null);
        $pairs = [];
        if (isset($node['posList'])) {
            $pairs = array_merge($pairs, $this->parseGmlPosList($this->firstScalar($node['posList']), $srsName));
        }
        if (isset($node['pos'])) {
            $pos = $node['pos'];
            if (is_array($pos) && !isset($pos['value']) && array_is_list($pos)) {
                foreach ($pos as $item) {
                    $pairs = array_merge($pairs, $this->parseGmlPosList($this->firstScalar($item), $srsName));
                }
            } else {
                $pairs = array_merge($pairs, $this->parseGmlPosList($this->firstScalar($pos), $srsName));
            }
        }

        return $pairs;
    }

    /**
     * GML posList / pos for EPSG:4326 is lat lon (confirmed on Autobahn MIA).
     * GeoJSON wants lon, lat.
     *
     * @return list<array{0: float, 1: float}>
     */
    private function parseGmlPosList(string $text, string $srsName): array
    {
        $normalized = trim((string) preg_replace('/[,\s]+/', ' ', $text));
        if ($normalized === '') {
            return [];
        }
        $nums = [];
        foreach (explode(' ', $normalized) as $part) {
            if ($part !== '' && is_numeric($part)) {
                $nums[] = (float) $part;
            }
        }
        if (count($nums) < 2 || count($nums) % 2 !== 0) {
            return [];
        }
        if (!$this->gmlIsGeographic($srsName)) {
            return [];
        }
        $latFirst = $this->gmlIsLatLon($srsName, $nums);
        $points = [];
        for ($i = 0; $i < count($nums); $i += 2) {
            $first = $nums[$i];
            $second = $nums[$i + 1];
            $lat = $latFirst ? $first : $second;
            $lon = $latFirst ? $second : $first;
            if ($lat === 0.0 && $lon === 0.0) {
                continue;
            }
            $points[] = [$lon, $lat];
        }

        return $points;
    }

    private function gmlIsGeographic(string $srsName): bool
    {
        $srs = strtolower($srsName);

        return $srs === ''
            || str_contains($srs, '4326')
            || str_contains($srs, 'wgs84')
            || str_contains($srs, 'crs84');
    }

    /**
     * @param list<float> $nums
     */
    private function gmlIsLatLon(string $srsName, array $nums): bool
    {
        $srs = strtolower($srsName);
        if (str_contains($srs, 'crs84')) {
            return false;
        }
        if (str_contains($srs, '4326') || str_contains($srs, 'wgs84')) {
            return true;
        }
        $first = $nums[0];
        $second = $nums[1];
        $firstLooksLat = $first >= 47.0 && $first <= 55.5;
        $secondLooksLon = $second >= 5.0 && $second <= 16.0;
        $firstLooksLon = $first >= 5.0 && $first <= 16.0;
        $secondLooksLat = $second >= 47.0 && $second <= 55.5;
        if ($firstLooksLat && $secondLooksLon) {
            return true;
        }
        if ($firstLooksLon && $secondLooksLat) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed>|null $node
     * @return array{0: float, 1: float}|null
     */
    private function latLonPair(?array $node): ?array
    {
        if ($node === null || !isset($node['latitude'], $node['longitude'])) {
            return null;
        }
        $lat = (float) $node['latitude'];
        $lon = (float) $node['longitude'];
        if ($lat === 0.0 && $lon === 0.0) {
            return null;
        }

        return [$lon, $lat];
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>|null
     */
    private function firstNodeByKey(array $node, string $key): ?array
    {
        if (isset($node[$key]) && is_array($node[$key])) {
            $maps = DatexRoadworksXml::listOfMaps($node[$key]);

            return $maps[0] ?? $node[$key];
        }
        foreach ($node as $childKey => $value) {
            if ($childKey === '@attributes' || !is_array($value)) {
                continue;
            }
            foreach (DatexRoadworksXml::listOfMaps($value) as $child) {
                $found = $this->firstNodeByKey($child, $key);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function pointInBbox(float $lon, float $lat): bool
    {
        [$minLon, $minLat, $maxLon, $maxLat] = $this->bbox;

        return $lon >= $minLon && $lon <= $maxLon && $lat >= $minLat && $lat <= $maxLat;
    }

    private function firstScalar(mixed $value): string
    {
        if (is_array($value)) {
            if (isset($value['value']) && is_scalar($value['value'])) {
                return (string) $value['value'];
            }
            $maps = DatexRoadworksXml::listOfMaps($value);
            if ($maps !== []) {
                return $this->firstScalar($maps[0]);
            }
            if (array_is_list($value) && isset($value[0]) && is_scalar($value[0])) {
                return (string) $value[0];
            }

            return '';
        }

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function nullableBool(mixed $value): ?bool
    {
        $text = strtolower($this->firstScalar($value));
        if ($text === 'true' || $text === '1') {
            return true;
        }
        if ($text === 'false' || $text === '0') {
            return false;
        }

        return null;
    }

    private function isoOrNull(mixed $value): ?string
    {
        $text = $this->firstScalar($value);
        if ($text === '') {
            return null;
        }
        $timestamp = strtotime($text);
        if ($timestamp === false) {
            return $text;
        }

        return date(DATE_ATOM, $timestamp);
    }

    private static function firstLine(string $text): string
    {
        $line = trim(explode("\n", $text)[0] ?? '');

        return $line !== '' ? $line : $text;
    }

    private static function normalizeId(string $id): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim($id)) ?: 'unknown';
    }
}
