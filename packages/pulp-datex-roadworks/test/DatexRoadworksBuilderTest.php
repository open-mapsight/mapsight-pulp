<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexroadworks\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpDatexRoadworks;
use OpenMapsight\pulpdatexroadworks\DatexRoadworksBuilder;
use PHPUnit\Framework\TestCase;

class DatexRoadworksBuilderTest extends TestCase
{
    /** Autobahn approaches around Braunschweig. */
    private const BBOX = [10.30, 52.12, 10.80, 52.42];

    public function testBuildEmitsOneFeaturePerInBboxAldRecord(): void
    {
        $geoJson = $this->createBuilder()->build($this->aldXml());

        $this->assertSame('FeatureCollection', $geoJson['type']);
        $this->assertSame([
            'name' => 'DATEX Roadworks',
            'url' => 'https://public.example/datex-roadworks',
            'documentationUrl' => 'https://docs.example/datex-roadworks',
            'bbox' => self::BBOX,
        ], $geoJson['source']);
        $this->assertCount(1, $geoJson['features']);

        $feature = $this->featureById($geoJson, 'datex-roadworks-ald-REC-A2-BS');
        $this->assertSame('Point', $feature['geometry']['type']);
        $this->assertEqualsWithDelta([10.5234, 52.3123], $feature['geometry']['coordinates'], 0.0001);
        $this->assertSame('REC-A2-BS', $feature['properties']['recordId']);
        $this->assertSame('SIT-IN', $feature['properties']['situationId']);
        $this->assertSame('A2 AS Braunschweig-Nord', $feature['properties']['name']);
        $this->assertSame('Fahrbahninstandsetzung A2, eine Spur gesperrt.', $feature['properties']['comment']);
        $this->assertSame('Roadworks', $feature['properties']['recordType']);
        $this->assertSame('ald', $feature['properties']['feedKind']);
        $this->assertSame('repair', $feature['properties']['roadMaintenanceType']);
        $this->assertSame('longTerm', $feature['properties']['roadworksDuration']);
        $this->assertTrue($feature['properties']['underTraffic']);
        $this->assertSame('DATEX Roadworks', $feature['properties']['source']);
        $this->assertSame('https://public.example/datex-roadworks', $feature['properties']['sourceUrl']);
        $this->assertArrayNotHasKey('description', $feature['properties']);
        $this->assertArrayNotHasKey('mapsightIconId', $feature['properties']);
    }

    public function testRecordsInBboxDropsOutsideCoordinates(): void
    {
        $ids = array_map(
            static fn(array $record): string => (string) ($record['id'] ?? ''),
            $this->createBuilder()->recordsInBbox($this->aldXml())
        );

        $this->assertSame(['REC-A2-BS'], $ids);
    }

    public function testGmlPosListBecomesGeoJsonLonLatAndDropsOutsideLine(): void
    {
        $geoJson = $this->createBuilder()->build($this->gmlXml());

        $this->assertCount(1, $geoJson['features']);
        $feature = $this->featureById($geoJson, 'datex-roadworks-ald-REC-A2-GML');
        $this->assertSame('A2 AS Braunschweig-Nord', $feature['properties']['name']);
        $type = $feature['geometry']['type'];
        $this->assertContains($type, ['LineString', 'GeometryCollection']);
        $line = $type === 'GeometryCollection'
            ? ($feature['geometry']['geometries'][1]['coordinates'] ?? [])
            : ($feature['geometry']['coordinates'] ?? []);
        $this->assertIsArray($line[0] ?? null);
        $this->assertEqualsWithDelta(10.5234, $line[0][0], 0.0001);
        $this->assertEqualsWithDelta(52.3123, $line[0][1], 0.0001);
    }

    public function testFeedKindOptionPrefixesFeatureId(): void
    {
        $geoJson = PulpDatexRoadworks::roadworksBuilder(
            self::BBOX,
            'https://internal.example/datex-roadworks',
            'DATEX Roadworks',
            'https://docs.example/datex-roadworks',
            'https://public.example/datex-roadworks',
            ['feedKind' => 'akd'],
        )->build($this->akdXml());

        $this->assertCount(1, $geoJson['features']);
        $this->assertSame('datex-roadworks-akd-REC-A39-BS', $geoJson['features'][0]['id']);
        $this->assertSame('akd', $geoJson['features'][0]['properties']['feedKind']);
        $this->assertSame('A39 AS Braunschweig-Süd', $geoJson['features'][0]['properties']['name']);
        $this->assertSame('roadMarkingWork', $geoJson['features'][0]['properties']['roadMaintenanceType']);
        $this->assertSame('shortTerm', $geoJson['features'][0]['properties']['roadworksDuration']);
    }

    public function testLocationNameFallsBackToRoadNumber(): void
    {
        $geoJson = $this->createBuilder()->build($this->gmlXml());
        $a10 = null;
        foreach ($this->createBuilder(self::BBOX)->recordsInBbox($this->gmlXml()) as $record) {
            if (($record['id'] ?? '') === 'REC-A10-BER') {
                $a10 = $record;
            }
        }

        $this->assertNull($a10);
        $this->assertSame('A2 AS Braunschweig-Nord', $geoJson['features'][0]['properties']['name']);
    }

    public function testBuildAcceptsAlreadyDecodedPublication(): void
    {
        $file = new File('roadworks.xml');
        $file->content = $this->aldXml();
        $decoded = \OpenMapsight\pulpdatexroadworks\GeoJsonHandler::publicationFromFile($file);

        $geoJson = $this->createBuilder()->build($decoded);

        $this->assertCount(1, $geoJson['features']);
    }

    public function testGeoJsonHandlerConsumesXmlString(): void
    {
        $file = new File('roadworks.xml');
        $file->content = $this->aldXml();

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpDatexRoadworks::roadworksGeoJson(
                self::BBOX,
                'https://internal.example/datex-roadworks',
                'DATEX Roadworks',
                'https://docs.example/datex-roadworks',
                'https://public.example/datex-roadworks',
                ['feedKind' => 'ald'],
            ))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('datex-roadworks.geojson', $res[0]->fileName);
        $this->assertSame('FeatureCollection', $res[0]->content['type']);
        $this->assertCount(1, $res[0]->content['features']);
        $this->assertSame('A2 AS Braunschweig-Nord', $res[0]->content['features'][0]['properties']['name']);
    }

    /**
     * @param array<string, mixed> $geoJson
     * @return array<string, mixed>
     */
    private function featureById(array $geoJson, string $id): array
    {
        foreach ($geoJson['features'] as $feature) {
            if (($feature['id'] ?? null) === $id) {
                return $feature;
            }
        }

        $this->fail(sprintf('Feature "%s" was not found.', $id));
    }

    private function createBuilder(array $bbox = self::BBOX): DatexRoadworksBuilder
    {
        return PulpDatexRoadworks::roadworksBuilder(
            $bbox,
            'https://internal.example/datex-roadworks',
            'DATEX Roadworks',
            'https://docs.example/datex-roadworks',
            'https://public.example/datex-roadworks',
            ['feedKind' => 'ald'],
        );
    }

    private function aldXml(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/datex-roadworks-ald.xml');
    }

    private function gmlXml(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/datex-roadworks-gml.xml');
    }

    private function akdXml(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/datex-roadworks-akd.xml');
    }
}
