<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgtfs\dev\test;

use FilesystemIterator;
use OpenMapsight\Pulp;
use OpenMapsight\PulpGTFS;
use OpenMapsight\pulpgtfs\GtfsGeoJsonBuilder;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class GtfsGeoJsonBuilderTest extends TestCase
{
    private array $tmpDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $tmpDir) {
            $this->removeDirectory($tmpDir);
        }

        $this->tmpDirs = [];
    }

    public function testBuildCombinedGeoJson(): void
    {
        $builder = $this->createBuilder();

        $geoJson = $builder->build($this->createGtfsDirectory(), 'combined');

        $this->assertSame('FeatureCollection', $geoJson['type']);
        $this->assertSame([
            'name' => 'Source GTFS',
            'url' => 'https://public.example/gtfs',
            'documentationUrl' => 'https://docs.example/gtfs',
            'bbox' => [10.4, 52.1, 10.7, 52.4],
        ], $geoJson['source']);
        $this->assertCount(4, $geoJson['features']);

        $centralStop = $this->featureById($geoJson, 'gtfs-stop-S-1');
        $this->assertSame('Point', $centralStop['geometry']['type']);
        $this->assertSame([10.55, 52.25], $centralStop['geometry']['coordinates']);
        $this->assertSame('S:1', $centralStop['properties']['gtfsStopId']);
        $this->assertSame('Central Station', $centralStop['properties']['name']);
        $this->assertSame(['2 Campus', '10 City Loop'], $centralStop['properties']['lines']);
        $this->assertSame(['R2', 'R10'], $centralStop['properties']['routeIds']);
        $this->assertSame(['bus', 'tram'], $centralStop['properties']['modes']);
        $this->assertSame(['Bus', 'Tram'], $centralStop['properties']['modeLabels']);
        $this->assertSame('yes', $centralStop['properties']['wheelchairBoarding']);
        $this->assertTrue($centralStop['properties']['wheelchairAccessible']);
        $this->assertSame('https://live.example/departures?tenant=nds&stop_id=S%3A1', $centralStop['properties']['departuresUrl']);
        $this->assertSame('https://public.example/gtfs', $centralStop['properties']['sourceUrl']);

        $townHallStop = $this->featureById($geoJson, 'gtfs-stop-S2');
        $this->assertSame(['10 City Loop'], $townHallStop['properties']['lines']);
        $this->assertSame('no', $townHallStop['properties']['wheelchairBoarding']);
        $this->assertFalse($townHallStop['properties']['wheelchairAccessible']);

        $busRoute = $this->featureById($geoJson, 'gtfs-route-R10');
        $this->assertSame('LineString', $busRoute['geometry']['type']);
        $this->assertSame([
            [10.5, 52.2],
            [10.56, 52.26],
            [10.6, 52.3],
        ], $busRoute['geometry']['coordinates']);
        $this->assertSame('shape_bus_main', $busRoute['properties']['gtfsShapeId']);
        $this->assertSame('Linie 10 City Loop', $busRoute['properties']['name']);
        $this->assertSame('bus', $busRoute['properties']['mode']);
        $this->assertSame('#00AEEF', $busRoute['properties']['stroke']);

        $tramRoute = $this->featureById($geoJson, 'gtfs-route-R2');
        $this->assertSame('tram', $tramRoute['properties']['mode']);
        $this->assertArrayNotHasKey('stroke', $tramRoute['properties']);
    }

    public function testBuildFileReturnsNamedPulpFile(): void
    {
        $file = $this->createBuilder()->buildFile($this->createGtfsDirectory(), 'stops');

        $this->assertSame('gtfs-stops.geojson', $file->fileName);
        $this->assertSame('FeatureCollection', $file->content['type']);
        $this->assertCount(2, $file->content['features']);
    }

    public function testGeoJsonHandlerConsumesIncomingFiles(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src('.*\.txt', $this->createGtfsDirectory()))
            ->pipe(PulpGTFS::geoJson(
                'combined',
                'https://internal.example/gtfs',
                [10.4, 52.1, 10.7, 52.4],
                'https://live.example/departures?tenant=nds',
                'Source GTFS',
                'https://docs.example/gtfs',
                'https://public.example/gtfs'
            ))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('gtfs-combined.geojson', $res[0]->fileName);
        $this->assertSame('FeatureCollection', $res[0]->content['type']);
        $this->assertCount(4, $res[0]->content['features']);
    }

    public function testBuildLinesCanFallbackToStopLineStringsWithoutShapes(): void
    {
        $gtfsDirectory = $this->createGtfsDirectory();
        unlink($gtfsDirectory . '/shapes.txt');

        $geoJson = $this->createBuilder(fallbackLineStringsFromStops: true)->build($gtfsDirectory, 'lines');

        $this->assertCount(1, $geoJson['features']);
        $busRoute = $this->featureById($geoJson, 'gtfs-route-R10');
        $this->assertSame('LineString', $busRoute['geometry']['type']);
        $this->assertSame([
            [10.55, 52.25],
            [10.57, 52.27],
        ], $busRoute['geometry']['coordinates']);
        $this->assertNull($busRoute['properties']['gtfsShapeId']);
        $this->assertSame('stops', $busRoute['properties']['geometrySource']);
    }

    public function testGeoJsonHandlerCanFallbackToStopLineStringsWithoutShapesFile(): void
    {
        $gtfsDirectory = $this->createGtfsDirectory();
        unlink($gtfsDirectory . '/shapes.txt');

        $res = Pulp::start()
            ->pipe(Pulp::src('.*\.txt', $gtfsDirectory))
            ->pipe(PulpGTFS::geoJson(
                'lines',
                'https://internal.example/gtfs',
                [10.4, 52.1, 10.7, 52.4],
                null,
                'Source GTFS',
                'https://docs.example/gtfs',
                'https://public.example/gtfs',
                [
                    'fallbackLineStringsFromStops' => true,
                ]
            ))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('gtfs-lines.geojson', $res[0]->fileName);
        $this->assertCount(1, $res[0]->content['features']);
        $this->assertSame('stops', $res[0]->content['features'][0]['properties']['geometrySource']);
    }

    public function testGeoJsonHandlerSupportsCustomFileNames(): void
    {
        $gtfsDirectory = $this->createGtfsDirectory();
        rename($gtfsDirectory . '/routes.txt', $gtfsDirectory . '/my-routes.csv');

        $res = Pulp::start()
            ->pipe(Pulp::src('.*\.(txt|csv)', $gtfsDirectory))
            ->pipe(PulpGTFS::geoJson(
                'stops',
                'https://internal.example/gtfs',
                [10.4, 52.1, 10.7, 52.4],
                null,
                'Source GTFS',
                'https://docs.example/gtfs',
                'https://public.example/gtfs',
                [
                    'files' => [
                        'routes' => 'my-routes.csv',
                    ],
                ]
            ))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('gtfs-stops.geojson', $res[0]->fileName);
        $this->assertCount(2, $res[0]->content['features']);
    }

    public function testBuildRejectsUnsupportedType(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('type must be one of: stops, lines, combined');

        $this->createBuilder()->buildFile($this->createGtfsDirectory(), 'stations');
    }

    private function createBuilder(bool $fallbackLineStringsFromStops = false): GtfsGeoJsonBuilder
    {
        return new GtfsGeoJsonBuilder(
            'https://internal.example/gtfs',
            [10.4, 52.1, 10.7, 52.4],
            'https://live.example/departures?tenant=nds',
            'Source GTFS',
            'https://docs.example/gtfs',
            'https://public.example/gtfs',
            $fallbackLineStringsFromStops
        );
    }

    private function featureById(array $geoJson, string $id): array
    {
        foreach ($geoJson['features'] as $feature) {
            if (($feature['id'] ?? null) === $id) {
                return $feature;
            }
        }

        $this->fail(sprintf('Feature "%s" was not found.', $id));
    }

    private function createGtfsDirectory(): string
    {
        $tmpDir = sys_get_temp_dir() . '/pulp-gtfs-test-' . uniqid('', true);
        $this->assertTrue(mkdir($tmpDir));
        $this->tmpDirs[] = $tmpDir;

        $this->writeCsv($tmpDir, 'routes.txt', [
            ['route_id', 'route_short_name', 'route_long_name', 'route_type', 'route_color', 'route_url'],
            ['R10', '10', 'City Loop', '3', '00AEEF', 'https://example.test/routes/10'],
            ['R2', '2', 'Campus', '0', '', 'https://example.test/routes/2'],
            ['R99', '99', 'Outside', '3', 'FF0000', 'https://example.test/routes/99'],
        ]);
        $this->writeCsv($tmpDir, 'stops.txt', [
            ['stop_id', 'stop_name', 'stop_lat', 'stop_lon', 'location_type', 'parent_station', 'wheelchair_boarding', 'stop_code'],
            ['PARENT', 'Central Parent', '52.25', '10.55', '1', '', '1', ''],
            ['S:1', 'Central Station', '52.25', '10.55', '0', 'PARENT', '0', '1001'],
            ['S2', 'Town Hall', '52.27', '10.57', '0', '', '2', '1002'],
            ['S3', 'Outside Stop', '53.0', '11.0', '0', '', '1', '1003'],
        ]);
        $this->writeCsv($tmpDir, 'stop_times.txt', [
            ['trip_id', 'arrival_time', 'departure_time', 'stop_id', 'stop_sequence'],
            ['T_BUS_1', '08:00:00', '08:00:00', 'S:1', '1'],
            ['T_BUS_1', '08:05:00', '08:05:00', 'S2', '2'],
            ['T_BUS_2', '09:00:00', '09:00:00', 'S2', '1'],
            ['T_BUS_ALT', '10:00:00', '10:00:00', 'S:1', '1'],
            ['T_TRAM', '11:00:00', '11:00:00', 'S:1', '1'],
            ['T_OUTSIDE', '12:00:00', '12:00:00', 'S3', '1'],
        ]);
        $this->writeCsv($tmpDir, 'trips.txt', [
            ['route_id', 'service_id', 'trip_id', 'shape_id'],
            ['R10', 'weekday', 'T_BUS_1', 'shape_bus_main'],
            ['R10', 'weekday', 'T_BUS_2', 'shape_bus_main'],
            ['R10', 'weekday', 'T_BUS_ALT', 'shape_bus_alt'],
            ['R2', 'weekday', 'T_TRAM', 'shape_tram'],
            ['R99', 'weekday', 'T_OUTSIDE', 'shape_outside'],
        ]);
        $this->writeCsv($tmpDir, 'shapes.txt', [
            ['shape_id', 'shape_pt_lat', 'shape_pt_lon', 'shape_pt_sequence'],
            ['shape_bus_main', '52.3', '10.6', '3'],
            ['shape_bus_main', '52.2', '10.5', '1'],
            ['shape_bus_main', '52.26', '10.56', '2'],
            ['shape_bus_alt', '52.21', '10.51', '1'],
            ['shape_bus_alt', '52.22', '10.52', '2'],
            ['shape_tram', '52.24', '10.54', '1'],
            ['shape_tram', '52.28', '10.58', '2'],
            ['shape_outside', '53.0', '11.0', '1'],
            ['shape_outside', '53.1', '11.1', '2'],
        ]);

        return $tmpDir;
    }

    private function writeCsv(string $directory, string $fileName, array $rows): void
    {
        $stream = fopen($directory . '/' . $fileName, 'wb');
        $this->assertIsResource($stream);

        foreach ($rows as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }

        fclose($stream);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($directory);
    }
}
