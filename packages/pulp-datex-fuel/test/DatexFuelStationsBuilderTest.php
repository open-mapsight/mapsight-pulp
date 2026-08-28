<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexfuel\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpDatexFuel;
use OpenMapsight\pulpdatexfuel\DatexFuelStationsBuilder;
use PHPUnit\Framework\TestCase;

class DatexFuelStationsBuilderTest extends TestCase
{
    /** Official example stations sit near Aachen (ETRS89). */
    private const BBOX = [6.05, 50.76, 6.08, 50.82];

    public function testBuildEmitsOneFeaturePerOfficialStationInBbox(): void
    {
        $geoJson = $this->createBuilder()->build($this->officialStationsXml());

        $this->assertSame('FeatureCollection', $geoJson['type']);
        $this->assertSame([
            'name' => 'DATEX Fuel',
            'url' => 'https://public.example/datex-fuel',
            'documentationUrl' => 'https://docs.example/datex-fuel',
            'bbox' => self::BBOX,
        ], $geoJson['source']);
        $this->assertCount(2, $geoJson['features']);

        $muster = $this->featureById($geoJson, 'datex-fuel-station-69C7386C-F20D-4872-96A6-B30DE28C852D');
        $this->assertSame('Point', $muster['geometry']['type']);
        $this->assertEqualsWithDelta([6.062009, 50.807374], $muster['geometry']['coordinates'], 0.000001);
        $this->assertSame('69C7386C-F20D-4872-96A6-B30DE28C852D', $muster['properties']['stationId']);
        $this->assertSame('Mustertankstelle', $muster['properties']['name']);
        $this->assertSame('Mustermarke', $muster['properties']['brand']);
        $this->assertSame('', $muster['properties']['operator']);
        $this->assertSame('Musterstraße 1', $muster['properties']['addressLine']);
        $this->assertSame('12345', $muster['properties']['postcode']);
        $this->assertSame('Musterort', $muster['properties']['city']);
        $this->assertSame('DE', $muster['properties']['countryCode']);
        $this->assertSame('2013-05-03T09:00:00+02:00', $muster['properties']['stationUpdatedAt']);
        $this->assertSame('DATEX Fuel', $muster['properties']['source']);
        $this->assertSame('https://public.example/datex-fuel', $muster['properties']['sourceUrl']);
        $this->assertSame([
            [
                'days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'periods' => [
                    ['start' => '06:00:00', 'end' => '12:00:00'],
                    ['start' => '13:00:00', 'end' => '20:00:00'],
                ],
            ],
            [
                'days' => ['saturday', 'sunday', 'publicHoliday'],
                'periods' => [
                    ['start' => '08:00:00', 'end' => '12:00:00'],
                ],
            ],
        ], $muster['properties']['openingHours']);
        $this->assertArrayNotHasKey('e5', $muster['properties']);
        $this->assertArrayNotHasKey('description', $muster['properties']);
        $this->assertArrayNotHasKey('mapsightIconId', $muster['properties']);

        $beispiel = $this->featureById($geoJson, 'datex-fuel-station-110326DC-269E-4042-9588-6248BB610394');
        $this->assertSame('Beispieltankstelle2', $beispiel['properties']['name']);
        $this->assertSame('Beispielmarke2', $beispiel['properties']['brand']);
        $this->assertSame('Beispielweg 2', $beispiel['properties']['addressLine']);
        $this->assertSame('22222', $beispiel['properties']['postcode']);
        $this->assertSame('Beispielort', $beispiel['properties']['city']);
        $this->assertEqualsWithDelta([6.072466, 50.772529], $beispiel['geometry']['coordinates'], 0.000001);
    }

    public function testStationsInBboxDropsOutsideZeroAndMissingGeometry(): void
    {
        $officialIds = array_map(
            static fn(array $station): string => (string) ($station['id'] ?? ''),
            $this->createBuilder()->stationsInBbox($this->officialStationsXml())
        );
        $edgeIds = array_map(
            static fn(array $station): string => (string) ($station['id'] ?? ''),
            $this->createBuilder()->stationsInBbox($this->edgeStationsXml())
        );

        $this->assertSame([
            '69C7386C-F20D-4872-96A6-B30DE28C852D',
            '110326DC-269E-4042-9588-6248BB610394',
        ], $officialIds);
        $this->assertSame([], $edgeIds);
    }

    public function testTightBboxKeepsOnlyMustertankstelle(): void
    {
        $geoJson = PulpDatexFuel::stationsBuilder([6.055, 50.80, 6.070, 50.81])
            ->build($this->officialStationsXml());

        $this->assertCount(1, $geoJson['features']);
        $this->assertSame('Mustertankstelle', $geoJson['features'][0]['properties']['name']);
    }

    public function testFeatureFromStationSkipsStationsWithoutCoordinates(): void
    {
        $station = null;
        foreach ($this->createBuilder()->stationsInBbox($this->edgeStationsXml()) as $candidate) {
            if (($candidate['id'] ?? '') === 'MISSING-COORD-0000-0000-000000000002') {
                $station = $candidate;
            }
        }

        $this->assertNull($station);
        $this->assertNull($this->createBuilder()->featureFromStation([
            'id' => 'MISSING-COORD-0000-0000-000000000002',
            'petrolStationName' => 'Missing Coord',
        ]));
    }

    public function testBuildUnwrapsSoapContainer(): void
    {
        $fromSoap = $this->createBuilder()->build($this->soapStationsXml());

        $this->assertCount(1, $fromSoap['features']);
        $this->assertSame('Mustertankstelle', $fromSoap['features'][0]['properties']['name']);
        $this->assertSame('69C7386C-F20D-4872-96A6-B30DE28C852D', $fromSoap['features'][0]['properties']['stationId']);
    }

    public function testBuildAcceptsOfficialMinimumPublication(): void
    {
        $geoJson = $this->createBuilder()->build(
            (string) file_get_contents(__DIR__ . '/fixtures/petrolStationExample_Minimum_v01-01-00.xml')
        );

        $this->assertCount(1, $geoJson['features']);
        $this->assertSame('Mustertankstelle', $geoJson['features'][0]['properties']['name']);
        $this->assertSame('', $geoJson['features'][0]['properties']['brand']);
        $this->assertSame([
            [
                'days' => ['monday'],
                'periods' => [
                    ['start' => '06:00:00', 'end' => '12:00:00'],
                ],
            ],
        ], $geoJson['features'][0]['properties']['openingHours']);
    }

    public function testBuildAcceptsAlreadyDecodedPublication(): void
    {
        $file = new File('stations.xml');
        $file->content = $this->officialStationsXml();
        $decoded = \OpenMapsight\pulpdatexfuel\GeoJsonHandler::publicationFromFile($file);

        $geoJson = $this->createBuilder()->build($decoded);

        $this->assertCount(2, $geoJson['features']);
    }

    public function testGeoJsonHandlerConsumesXmlString(): void
    {
        $file = new File('stations.xml');
        $file->content = $this->officialStationsXml();

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpDatexFuel::stationsGeoJson(
                self::BBOX,
                'https://internal.example/datex-fuel',
                'DATEX Fuel',
                'https://docs.example/datex-fuel',
                'https://public.example/datex-fuel'
            ))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('datex-fuel-stations.geojson', $res[0]->fileName);
        $this->assertSame('FeatureCollection', $res[0]->content['type']);
        $this->assertCount(2, $res[0]->content['features']);
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

    private function createBuilder(): DatexFuelStationsBuilder
    {
        return PulpDatexFuel::stationsBuilder(
            self::BBOX,
            'https://internal.example/datex-fuel',
            'DATEX Fuel',
            'https://docs.example/datex-fuel',
            'https://public.example/datex-fuel'
        );
    }

    private function officialStationsXml(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/petrolStationExample_v01-01-00.xml');
    }

    private function edgeStationsXml(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/petrol-stations-edge.xml');
    }

    private function soapStationsXml(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/petrol-station-soap.xml');
    }
}
