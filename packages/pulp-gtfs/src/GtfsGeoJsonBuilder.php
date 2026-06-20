<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgtfs;

use OpenMapsight\pulp\File;
use RuntimeException;

class GtfsGeoJsonBuilder
{
    public const SUPPORTED_TYPES = ['stops', 'lines', 'combined'];

    public function __construct(
        private readonly string $sourceUrl,
        private readonly array $bbox,
        private readonly ?string $departuresBaseUrl = null,
        private readonly string $sourceName = 'GTFS',
        private readonly ?string $documentationUrl = null,
        private readonly ?string $publicSourceUrl = null,
        private readonly bool $fallbackLineStringsFromStops = false,
    ) {
    }

    public function buildFile(string $gtfsDirectory, string $type): File
    {
        $this->assertSupportedType($type);

        $file = new File('gtfs-' . $type . '.geojson');
        $file->content = $this->build($gtfsDirectory, $type);

        return $file;
    }

    public function build(string $gtfsDirectory, string $type): array
    {
        return $this->buildFromReader(new GtfsDirectoryReader($gtfsDirectory), $type);
    }

    public function buildFromReader(GtfsReader $gtfs, string $type): array
    {
        $this->assertSupportedType($type);

        $routes = $this->loadRoutes($gtfs);
        $stops = $this->loadTargetStops($gtfs);
        $needsStopRoutes = $type === 'stops' || $type === 'combined';
        $needsShapes = $type === 'lines' || $type === 'combined';
        $needsFallbackLines = $needsShapes && $this->fallbackLineStringsFromStops;
        [$stopIdsByTrip, $targetTripIds, $stopSequencesByTrip] = $this->loadTargetTripIds(
            $gtfs,
            $stops,
            $needsStopRoutes,
            $needsFallbackLines
        );
        [$stopRoutes, $shapeIdsByRoute, $fallbackCoordinatesByRoute] = $this->loadTargetTripRouteData(
            $gtfs,
            $stopIdsByTrip,
            $targetTripIds,
            $needsStopRoutes,
            $needsShapes,
            $needsFallbackLines,
            $stopSequencesByTrip,
            $stops
        );

        $features = [];
        if ($type === 'stops' || $type === 'combined') {
            $features = array_merge($features, $this->buildStopFeatures($stops, $stopRoutes, $routes));
        }
        if ($type === 'lines' || $type === 'combined') {
            $features = array_merge($features, $this->buildLineFeatures(
                $gtfs,
                $routes,
                $shapeIdsByRoute,
                $fallbackCoordinatesByRoute
            ));
        }

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

    private function loadRoutes(GtfsReader $gtfs): array
    {
        $routes = [];

        foreach ($gtfs->csvRows('routes.txt') as $route) {
            $routeId = (string)($route['route_id'] ?? '');
            if ($routeId === '') {
                continue;
            }

            $mode = $this->routeMode((string)($route['route_type'] ?? ''));
            $route['mode'] = $mode;
            $route['modeLabel'] = $this->modeLabel($mode);
            $route['lineLabel'] = $this->formatLineLabel($route);
            $routes[$routeId] = $route;
        }

        return $routes;
    }

    private function loadTargetStops(GtfsReader $gtfs): array
    {
        $stops = [];
        $parentWheelchairBoarding = [];

        foreach ($gtfs->csvRows('stops.txt') as $stop) {
            $stopId = (string)($stop['stop_id'] ?? '');
            if ($stopId === '') {
                continue;
            }

            if (($stop['location_type'] ?? '0') === '1') {
                $parentWheelchairBoarding[$stopId] = (string)($stop['wheelchair_boarding'] ?? '0');
            }

            $lat = (float)($stop['stop_lat'] ?? 0);
            $lon = (float)($stop['stop_lon'] ?? 0);
            $locationType = (string)($stop['location_type'] ?? '0');

            if (($locationType === '' || $locationType === '0') && $this->isInBbox($lon, $lat)) {
                $stops[$stopId] = $stop + [
                    'stop_lat_float' => $lat,
                    'stop_lon_float' => $lon,
                ];
            }
        }

        foreach ($stops as $stopId => $stop) {
            $parentStation = (string)($stop['parent_station'] ?? '');
            $stops[$stopId]['parent_wheelchair_boarding'] = $parentStation !== ''
                ? ($parentWheelchairBoarding[$parentStation] ?? null)
                : null;
        }

        return $stops;
    }

    private function loadTargetTripIds(
        GtfsReader $gtfs,
        array $stops,
        bool $keepStopIdsByTrip,
        bool $keepStopSequencesByTrip,
    ): array
    {
        $stopIdsByTrip = [];
        $stopSequencesByTrip = [];
        $targetTripIds = [];

        foreach ($gtfs->csvRows('stop_times.txt') as $stopTime) {
            $stopId = (string)($stopTime['stop_id'] ?? '');
            if (!isset($stops[$stopId])) {
                continue;
            }

            $tripId = (string)($stopTime['trip_id'] ?? '');
            if ($tripId === '') {
                continue;
            }

            if ($keepStopIdsByTrip) {
                $stopIdsByTrip[$tripId][$stopId] = true;
            }
            if ($keepStopSequencesByTrip) {
                $sequence = (string)($stopTime['stop_sequence'] ?? '');
                $sequenceKey = $sequence !== ''
                    ? (int)$sequence
                    : count($stopSequencesByTrip[$tripId] ?? []);
                $stopSequencesByTrip[$tripId][$sequenceKey] = $stopId;
            }
            $targetTripIds[$tripId] = true;
        }

        return [$stopIdsByTrip, $targetTripIds, $stopSequencesByTrip];
    }

    private function loadTargetTripRouteData(
        GtfsReader $gtfs,
        array $stopIdsByTrip,
        array $targetTripIds,
        bool $buildStopRoutes,
        bool $buildShapeIds,
        bool $buildFallbackLines,
        array $stopSequencesByTrip,
        array $stops,
    ): array
    {
        $stopRoutes = [];
        $shapeCountsByRoute = [];
        $fallbackCoordinatesByRoute = [];

        foreach ($gtfs->csvRows('trips.txt') as $trip) {
            $tripId = (string)($trip['trip_id'] ?? '');
            if (!isset($targetTripIds[$tripId])) {
                continue;
            }

            $routeId = (string)($trip['route_id'] ?? '');
            if ($routeId === '') {
                continue;
            }

            if ($buildStopRoutes) {
                foreach (array_keys($stopIdsByTrip[$tripId] ?? []) as $stopId) {
                    $stopRoutes[$stopId][$routeId] = true;
                }
            }

            $shapeId = (string)($trip['shape_id'] ?? '');
            if ($buildShapeIds && $shapeId !== '') {
                $shapeCountsByRoute[$routeId][$shapeId] = ($shapeCountsByRoute[$routeId][$shapeId] ?? 0) + 1;
            }

            if ($buildFallbackLines) {
                $coordinates = $this->stopCoordinatesForTrip($stopSequencesByTrip[$tripId] ?? [], $stops);
                if (count($coordinates) >= 2 && count($coordinates) > count($fallbackCoordinatesByRoute[$routeId] ?? [])) {
                    $fallbackCoordinatesByRoute[$routeId] = $coordinates;
                }
            }
        }

        $shapeIdsByRoute = [];
        foreach ($shapeCountsByRoute as $routeId => $shapeCounts) {
            arsort($shapeCounts, SORT_NUMERIC);
            $shapeIdsByRoute[$routeId] = (string)array_key_first($shapeCounts);
        }

        return [$stopRoutes, $shapeIdsByRoute, $fallbackCoordinatesByRoute];
    }

    private function buildStopFeatures(array $stops, array $stopRoutes, array $routes): array
    {
        $features = [];

        foreach ($stops as $stopId => $stop) {
            $stopId = (string)$stopId;
            $routeIds = array_map('strval', array_keys($stopRoutes[$stopId] ?? []));
            usort($routeIds, fn(string $a, string $b): int => strnatcmp(
                $this->routeSortValue($routes[$a] ?? ['route_short_name' => $a]),
                $this->routeSortValue($routes[$b] ?? ['route_short_name' => $b])
            ));

            $lineLabels = [];
            $modes = [];
            foreach ($routeIds as $routeId) {
                if (!isset($routes[$routeId])) {
                    continue;
                }

                $lineLabels[] = (string)$routes[$routeId]['lineLabel'];
                $modes[$routes[$routeId]['mode']] = true;
            }

            $modeList = array_keys($modes);
            sort($modeList);
            $wheelchair = $this->wheelchairBoardingInfo(
                (string)($stop['wheelchair_boarding'] ?? '0'),
                $stop['parent_wheelchair_boarding'] ?? null
            );
            $name = (string)($stop['stop_name'] ?? $stopId);
            $linesText = implode(', ', $lineLabels);
            $featureId = 'gtfs-stop-' . $this->normalizeGtfsId($stopId);

            $properties = [
                'id' => $featureId,
                'gtfsStopId' => $stopId,
                'name' => $name,
                'lines' => $lineLabels,
                'routeIds' => $routeIds,
                'modes' => $modeList,
                'modeLabels' => array_map(fn(string $mode): string => $this->modeLabel($mode), $modeList),
                'wheelchairBoarding' => $wheelchair['value'],
                'wheelchairBoardingLabel' => $wheelchair['label'],
                'wheelchairAccessible' => $wheelchair['accessible'],
                'stopCode' => (string)($stop['stop_code'] ?? ''),
                'parentStation' => (string)($stop['parent_station'] ?? ''),
                'source' => $this->sourceName,
                'sourceUrl' => $this->publicSourceUrl ?? $this->sourceUrl,
            ];

            $liveDeparturesUrl = $this->departuresUrl($stopId);
            if ($liveDeparturesUrl !== null) {
                $properties['departuresUrl'] = $liveDeparturesUrl;
            }

            $features[] = [
                'type' => 'Feature',
                'id' => $featureId,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [
                        (float)$stop['stop_lon_float'],
                        (float)$stop['stop_lat_float'],
                    ],
                ],
                'properties' => $properties,
            ];
        }

        usort($features, static fn(array $a, array $b): int => strnatcmp($a['properties']['name'], $b['properties']['name']));

        return $features;
    }

    private function buildLineFeatures(
        GtfsReader $gtfs,
        array $routes,
        array $shapeIdsByRoute,
        array $fallbackCoordinatesByRoute,
    ): array
    {
        if ($shapeIdsByRoute === [] && $fallbackCoordinatesByRoute === []) {
            return [];
        }

        $coordinatesByShape = $this->loadShapesForLines($gtfs, $shapeIdsByRoute, $fallbackCoordinatesByRoute !== []);
        $features = [];

        $routeIds = array_unique(array_merge(array_keys($shapeIdsByRoute), array_keys($fallbackCoordinatesByRoute)));
        foreach ($routeIds as $routeId) {
            $shapeId = $shapeIdsByRoute[$routeId] ?? null;
            $coordinates = $shapeId !== null ? ($coordinatesByShape[$shapeId] ?? []) : [];
            $usesFallbackGeometry = count($coordinates) < 2;
            if ($usesFallbackGeometry) {
                $coordinates = $fallbackCoordinatesByRoute[$routeId] ?? [];
            }

            if (!isset($routes[$routeId]) || count($coordinates) < 2) {
                continue;
            }

            $route = $routes[$routeId];
            $name = 'Linie ' . $route['lineLabel'];
            $mode = (string)$route['mode'];
            $color = trim((string)($route['route_color'] ?? ''));
            $featureId = 'gtfs-route-' . $this->normalizeGtfsId((string)$routeId);

            $properties = [
                'id' => $featureId,
                'gtfsRouteId' => (string)$routeId,
                'gtfsShapeId' => $usesFallbackGeometry ? null : $shapeId,
                'geometrySource' => $usesFallbackGeometry ? 'stops' : 'shapes',
                'name' => $name,
                'line' => (string)($route['lineLabel'] ?? ''),
                'mode' => $mode,
                'modeLabel' => $this->modeLabel($mode),
                'routeType' => (string)($route['route_type'] ?? ''),
                'routeShortName' => (string)($route['route_short_name'] ?? ''),
                'routeLongName' => (string)($route['route_long_name'] ?? ''),
                'routeUrl' => (string)($route['route_url'] ?? ''),
                'source' => $this->sourceName,
                'sourceUrl' => $this->publicSourceUrl ?? $this->sourceUrl,
            ];

            if ($color !== '') {
                $properties['stroke'] = '#' . ltrim($color, '#');
            }

            $features[] = [
                'type' => 'Feature',
                'id' => $featureId,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $coordinates,
                ],
                'properties' => $properties,
            ];
        }

        usort($features, static fn(array $a, array $b): int => strnatcmp($a['properties']['line'], $b['properties']['line']));

        return $features;
    }

    private function loadShapesForLines(GtfsReader $gtfs, array $shapeIdsByRoute, bool $allowMissingShapes): array
    {
        if ($shapeIdsByRoute === []) {
            return [];
        }

        try {
            return $this->loadShapes($gtfs, $shapeIdsByRoute);
        } catch (RuntimeException $exception) {
            if ($allowMissingShapes) {
                return [];
            }

            throw $exception;
        }
    }

    private function loadShapes(GtfsReader $gtfs, array $shapeIdsByRoute): array
    {
        $wantedShapeIds = array_flip(array_values($shapeIdsByRoute));
        $shapePoints = [];

        foreach ($gtfs->csvRows('shapes.txt') as $shape) {
            $shapeId = (string)($shape['shape_id'] ?? '');
            if (!isset($wantedShapeIds[$shapeId])) {
                continue;
            }

            $shapePoints[$shapeId][(int)($shape['shape_pt_sequence'] ?? 0)] = [
                (float)($shape['shape_pt_lon'] ?? 0),
                (float)($shape['shape_pt_lat'] ?? 0),
            ];
        }

        $coordinatesByShape = [];
        foreach ($shapePoints as $shapeId => $points) {
            ksort($points, SORT_NUMERIC);
            $coordinatesByShape[$shapeId] = array_values($points);
        }

        return $coordinatesByShape;
    }

    private function stopCoordinatesForTrip(array $stopIdsBySequence, array $stops): array
    {
        ksort($stopIdsBySequence, SORT_NUMERIC);

        $coordinates = [];
        foreach ($stopIdsBySequence as $stopId) {
            if (!isset($stops[$stopId])) {
                continue;
            }

            $coordinate = [
                (float)$stops[$stopId]['stop_lon_float'],
                (float)$stops[$stopId]['stop_lat_float'],
            ];

            if ($coordinates !== [] && $coordinate === $coordinates[array_key_last($coordinates)]) {
                continue;
            }

            $coordinates[] = $coordinate;
        }

        return $coordinates;
    }

    private function isInBbox(float $lon, float $lat): bool
    {
        return $lon >= $this->bbox[0] && $lon <= $this->bbox[2] && $lat >= $this->bbox[1] && $lat <= $this->bbox[3];
    }

    private function departuresUrl(string $stopId): ?string
    {
        if ($this->departuresBaseUrl === null) {
            return null;
        }

        $separator = str_contains($this->departuresBaseUrl, '?') ? '&' : '?';

        return $this->departuresBaseUrl . $separator . 'stop_id=' . rawurlencode($stopId);
    }

    private function routeMode(string $routeType): string
    {
        return match ((int)$routeType) {
            0 => 'tram',
            1 => 'subway',
            2 => 'rail',
            3 => 'bus',
            4 => 'ferry',
            5 => 'cable_tram',
            6 => 'aerial_lift',
            7 => 'funicular',
            11 => 'trolleybus',
            12 => 'monorail',
            default => 'public_transport',
        };
    }

    private function modeLabel(string $mode): string
    {
        return match ($mode) {
            'tram' => 'Tram',
            'subway' => 'Subway',
            'rail' => 'Rail',
            'bus' => 'Bus',
            'ferry' => 'Ferry',
            'trolleybus' => 'Trolleybus',
            default => 'Public transport',
        };
    }

    private function formatLineLabel(array $route): string
    {
        $shortName = trim((string)($route['route_short_name'] ?? ''));
        $longName = trim((string)($route['route_long_name'] ?? ''));

        if ($shortName !== '' && $longName !== '') {
            return $shortName . ' ' . $longName;
        }

        return $shortName !== '' ? $shortName : ($longName !== '' ? $longName : (string)$route['route_id']);
    }

    private function wheelchairBoardingInfo(string $value, ?string $parentValue): array
    {
        $effectiveValue = $value !== '' && $value !== '0' ? $value : ($parentValue ?? '0');

        return match ($effectiveValue) {
            '1' => [
                'value' => 'yes',
                'label' => 'accessible',
                'accessible' => true,
            ],
            '2' => [
                'value' => 'no',
                'label' => 'not accessible',
                'accessible' => false,
            ],
            default => [
                'value' => 'unknown',
                'label' => 'unknown',
                'accessible' => null,
            ],
        };
    }

    private function routeSortValue(array $route): string
    {
        return str_pad((string)($route['route_short_name'] ?? ''), 10, '0', STR_PAD_LEFT)
            . (string)($route['route_long_name'] ?? '');
    }

    private function normalizeGtfsId(string|int $id): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]+/', '-', trim((string)$id)) ?: 'unknown';
    }

    private function assertSupportedType(string $type): void
    {
        if (!in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new RuntimeException('type must be one of: ' . implode(', ', self::SUPPORTED_TYPES));
        }
    }
}
