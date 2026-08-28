<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexfuel\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpDatexFuel;
use PHPUnit\Framework\TestCase;

class DatexFuelPricesTest extends TestCase
{
    private const BBOX = [6.05, 50.76, 6.08, 50.82];

    public function testExtractPricesFromOfficialPublication(): void
    {
        $prices = PulpDatexFuel::extractPrices($this->officialPricesXml());
        $byStation = [];
        foreach ($prices as $price) {
            $byStation[$price['stationId']] = $price;
        }

        $this->assertCount(2, $prices);
        $this->assertEqualsWithDelta(1.60, $byStation['69C7386C-F20D-4872-96A6-B30DE28C852D']['e5'], 0.0001);
        $this->assertEqualsWithDelta(1.235, $byStation['69C7386C-F20D-4872-96A6-B30DE28C852D']['e10'], 0.0001);
        $this->assertEqualsWithDelta(1.40, $byStation['69C7386C-F20D-4872-96A6-B30DE28C852D']['diesel'], 0.0001);
        $this->assertSame('2013-05-04T18:00:00+02:00', $byStation['69C7386C-F20D-4872-96A6-B30DE28C852D']['updatedAt']);

        $this->assertEqualsWithDelta(1.609, $byStation['110326DC-269E-4042-9588-6248BB610394']['e5'], 0.0001);
        $this->assertNull($byStation['110326DC-269E-4042-9588-6248BB610394']['e10']);
        $this->assertEqualsWithDelta(1.419, $byStation['110326DC-269E-4042-9588-6248BB610394']['diesel'], 0.0001);
        $this->assertSame('', $byStation['110326DC-269E-4042-9588-6248BB610394']['updatedAt']);
    }

    public function testExtractPricesFromOfficialMinimumPublication(): void
    {
        $prices = PulpDatexFuel::extractPrices(
            (string) file_get_contents(__DIR__ . '/fixtures/fuelPriceExample_Minimum_v01-01-00.xml')
        );

        $this->assertCount(1, $prices);
        $this->assertSame('69C7386C-F20D-4872-96A6-B30DE28C852D', $prices[0]['stationId']);
        $this->assertEqualsWithDelta(1.609, $prices[0]['e5'], 0.0001);
        $this->assertNull($prices[0]['e10']);
        $this->assertNull($prices[0]['diesel']);
        $this->assertSame('', $prices[0]['updatedAt']);
    }

    public function testExtractPricesAcceptsDecodedArray(): void
    {
        $file = new File('prices.xml');
        $file->content = $this->officialPricesXml();
        $decoded = \OpenMapsight\pulpdatexfuel\GeoJsonHandler::publicationFromFile($file);

        $prices = PulpDatexFuel::extractPrices($decoded);

        $this->assertCount(2, $prices);
        $this->assertSame('69C7386C-F20D-4872-96A6-B30DE28C852D', $prices[0]['stationId']);
    }

    public function testApplyPricePacketToCacheReplacesOnSnapshot(): void
    {
        $first = PulpDatexFuel::applyPricePacketToCache(
            [],
            [
                ['stationId' => 'OLD-GUID', 'e5' => 1.5, 'e10' => null, 'diesel' => null, 'updatedAt' => 't1'],
            ],
            'DELTA',
            'Mon, 01 Jan 2026 00:00:00 GMT'
        );
        $this->assertSame(1, $first['stationCount']);

        $snapshot = PulpDatexFuel::applyPricePacketToCache(
            $first,
            [
                ['stationId' => 'NEW-GUID', 'e5' => 1.539, 'e10' => 1.519, 'diesel' => 1.449, 'updatedAt' => 't2'],
            ],
            'SNAPSHOT',
            'Mon, 02 Jan 2026 00:00:00 GMT'
        );

        $this->assertSame('SNAPSHOT', $snapshot['packetType']);
        $this->assertArrayNotHasKey('OLD-GUID', $snapshot['prices']);
        $this->assertEqualsWithDelta(1.539, $snapshot['prices']['NEW-GUID']['e5'], 0.0001);
        $this->assertSame(1, $snapshot['stationCount']);
    }

    public function testDeltaPacketUpsertsByStationGuid(): void
    {
        $first = PulpDatexFuel::applyPricePacketToCache(
            [],
            [
                ['stationId' => 'A', 'e5' => 1.5, 'e10' => null, 'diesel' => null, 'updatedAt' => 't1'],
            ],
            'DELTA',
            't1'
        );
        $second = PulpDatexFuel::applyPricePacketToCache(
            $first,
            [
                ['stationId' => 'A', 'e5' => 1.6, 'e10' => null, 'diesel' => null, 'updatedAt' => 't2'],
                ['stationId' => 'B', 'e5' => null, 'e10' => 1.4, 'diesel' => null, 'updatedAt' => 't2'],
            ],
            'DELTA',
            't2'
        );

        $this->assertSame('DELTA', $second['packetType']);
        $this->assertEqualsWithDelta(1.6, $second['prices']['A']['e5'], 0.0001);
        $this->assertEqualsWithDelta(1.4, $second['prices']['B']['e10'], 0.0001);
        $this->assertSame(2, $second['stationCount']);
    }

    public function testMergePricesAddsNeutralPriceProperties(): void
    {
        $stations = PulpDatexFuel::stationsBuilder(self::BBOX)
            ->build($this->officialStationsXml());
        $prices = PulpDatexFuel::extractPrices($this->officialPricesXml());
        $cache = PulpDatexFuel::applyPricePacketToCache([], $prices, 'SNAPSHOT', 'now');

        $merged = PulpDatexFuel::mergePricesIntoFeatureCollection($stations, $cache['prices']);
        $this->assertSame(2, $merged['matched']);
        $this->assertSame(0, $merged['unmatched']);

        $muster = $this->featureById($merged['featureCollection'], 'datex-fuel-station-69C7386C-F20D-4872-96A6-B30DE28C852D');
        $this->assertEqualsWithDelta(1.60, $muster['properties']['e5'], 0.0001);
        $this->assertEqualsWithDelta(1.235, $muster['properties']['e10'], 0.0001);
        $this->assertEqualsWithDelta(1.40, $muster['properties']['diesel'], 0.0001);
        $this->assertSame('2013-05-04T18:00:00+02:00', $muster['properties']['pricesUpdatedAt']);
        $this->assertArrayNotHasKey('description', $muster['properties']);
        $this->assertArrayNotHasKey('markerCaption', $muster['properties']);

        $beispiel = $this->featureById($merged['featureCollection'], 'datex-fuel-station-110326DC-269E-4042-9588-6248BB610394');
        $this->assertEqualsWithDelta(1.609, $beispiel['properties']['e5'], 0.0001);
        $this->assertNull($beispiel['properties']['e10']);
        $this->assertEqualsWithDelta(1.419, $beispiel['properties']['diesel'], 0.0001);
    }

    public function testMergeLeavesUnmatchedStationWithoutPrices(): void
    {
        $stations = PulpDatexFuel::stationsBuilder(self::BBOX)
            ->build($this->officialStationsXml());
        $cache = PulpDatexFuel::applyPricePacketToCache(
            [],
            [
                ['stationId' => 'UNKNOWN-GUID', 'e5' => 1.0, 'e10' => null, 'diesel' => null, 'updatedAt' => 't'],
            ],
            'SNAPSHOT',
            'now'
        );

        $merged = PulpDatexFuel::mergePricesIntoFeatureCollection($stations, $cache['prices']);
        $this->assertSame(0, $merged['matched']);
        $this->assertSame(2, $merged['unmatched']);
        $this->assertArrayNotHasKey('e5', $merged['featureCollection']['features'][0]['properties']);
    }

    public function testPriceRecordsHandlerEmitsRecordsFromXmlString(): void
    {
        $file = new File('prices.xml');
        $file->content = $this->officialPricesXml();

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpDatexFuel::priceRecords())
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('datex-fuel-prices.json', $res[0]->fileName);
        $this->assertCount(2, $res[0]->content);
    }

    public function testMergePricesHandlerOverlaysCacheOntoFeatures(): void
    {
        $stations = PulpDatexFuel::stationsBuilder(self::BBOX)
            ->build($this->officialStationsXml());
        $prices = PulpDatexFuel::extractPrices($this->officialPricesXml());
        $cache = PulpDatexFuel::applyPricePacketToCache([], $prices, 'SNAPSHOT', 'now');

        $file = new File('stations.geojson');
        $file->content = $stations;

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpDatexFuel::mergePrices($cache['prices']))
            ->run();

        $muster = $this->featureById($res[0]->content, 'datex-fuel-station-69C7386C-F20D-4872-96A6-B30DE28C852D');
        $this->assertEqualsWithDelta(1.60, $muster['properties']['e5'], 0.0001);
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

    private function officialStationsXml(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/petrolStationExample_v01-01-00.xml');
    }

    private function officialPricesXml(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/fuelPriceExample_v01-01-00.xml');
    }
}
