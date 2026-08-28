<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexenergy\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpDatexEnergy;
use PHPUnit\Framework\TestCase;

class DatexEnergyStatusTest extends TestCase
{
    public function testExtractPointStatusesFromDeltaPublication(): void
    {
        $points = PulpDatexEnergy::extractPointStatuses($this->statusPublication());
        $byEvse = [];
        foreach ($points as $point) {
            $byEvse[$point['evseId']] = $point;
        }

        $this->assertCount(7, $points);
        $this->assertSame('available', $byEvse['DE*MUC*E*CO110897']['status']);
        $this->assertSame('charging', $byEvse['DE*MUC*E*CO110898']['status']);
        $this->assertSame('occupied', $byEvse['DE*LDK*E00122']['status']);
        $this->assertSame('reserved', $byEvse['DE*LDK*E00121']['status']);
        $this->assertSame('inoperative', $byEvse['DE*NMS*E510488*002']['status']);
        $this->assertSame('outOfOrder', $byEvse['DE*OUT*E00099']['status']);
        $this->assertSame('unknown', $byEvse['DE*UNK*E00001']['status']);
        $this->assertArrayNotHasKey('', $byEvse);
        $this->assertSame('site-ac-in-bbox', $byEvse['DE*MUC*E*CO110897']['siteId']);
        $this->assertSame('station-ac-1', $byEvse['DE*MUC*E*CO110897']['stationId']);
    }

    public function testApplyPacketToCacheReplacesOnSnapshot(): void
    {
        $first = PulpDatexEnergy::applyPacketToCache(
            [],
            [
                ['evseId' => 'DE*OLD*E1', 'status' => 'available', 'lastUpdated' => 't1', 'siteId' => 's1', 'stationId' => 'st1'],
            ],
            'DELTA',
            'Mon, 01 Jan 2026 00:00:00 GMT'
        );
        $this->assertSame(1, $first['pointCount']);

        $snapshot = PulpDatexEnergy::applyPacketToCache(
            $first,
            [
                ['evseId' => 'DE*NEW*E1', 'status' => 'charging', 'lastUpdated' => 't2', 'siteId' => 's2', 'stationId' => 'st2'],
            ],
            'SNAPSHOT',
            'Mon, 02 Jan 2026 00:00:00 GMT'
        );

        $this->assertSame('SNAPSHOT', $snapshot['packetType']);
        $this->assertArrayNotHasKey('DE*OLD*E1', $snapshot['points']);
        $this->assertSame('charging', $snapshot['points']['DE*NEW*E1']['status']);
        $this->assertSame(1, $snapshot['pointCount']);
    }

    public function testMergeStatusAddsNeutralAvailabilityProperties(): void
    {
        $sites = PulpDatexEnergy::sitesBuilder([10.42, 52.18, 10.65, 52.36])
            ->build($this->tablePublication());
        $points = PulpDatexEnergy::extractPointStatuses($this->statusPublication());
        $cache = PulpDatexEnergy::applyPacketToCache([], $points, 'DELTA', 'now');

        $merged = PulpDatexEnergy::mergeStatusIntoFeatureCollection($sites, $cache['points']);
        $this->assertSame(2, $merged['matched']);
        $this->assertSame(0, $merged['unmatched']);

        $ac = $this->featureById($merged['featureCollection'], 'datex-energy-site-site-ac-in-bbox');
        $this->assertSame('partial', $ac['properties']['chargingAvailability']);
        $this->assertSame(1, $ac['properties']['chargingAvailablePoints']);
        $this->assertSame(2, $ac['properties']['chargingObservedPoints']);
        $this->assertSame(['available' => 1, 'charging' => 1], $ac['properties']['chargingStatuses']);
        $this->assertSame('2026-08-28T10:15:10.298Z', $ac['properties']['chargingUpdatedAt']);
        $this->assertCount(2, $ac['properties']['evseStatuses']);
        $this->assertArrayNotHasKey('markerCaption', $ac['properties']);
        $this->assertArrayNotHasKey('tagGroups', $ac['properties']);
        $this->assertArrayNotHasKey('description', $ac['properties']);

        $dc = $this->featureById($merged['featureCollection'], 'datex-energy-site-9619ab5b-967f-4ad2-9d10-ee6be49cabce');
        $this->assertSame('occupied', $dc['properties']['chargingAvailability']);
        $this->assertSame(['occupied' => 1, 'reserved' => 1], $dc['properties']['chargingStatuses']);
    }

    public function testStatusHandlerEmitsRecordsFromJsonString(): void
    {
        $file = new File('status.json');
        $file->content = $this->statusPublicationJson();

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpDatexEnergy::statusRecords())
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('datex-energy-status.json', $res[0]->fileName);
        $this->assertCount(7, $res[0]->content);
    }

    public function testMergeStatusHandlerOverlaysCacheOntoFeatures(): void
    {
        $sites = PulpDatexEnergy::sitesBuilder([10.42, 52.18, 10.65, 52.36])
            ->build($this->tablePublication());
        $points = PulpDatexEnergy::extractPointStatuses($this->statusPublication());
        $cache = PulpDatexEnergy::applyPacketToCache([], $points, 'DELTA', 'now');

        $file = new File('sites.geojson');
        $file->content = $sites;

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpDatexEnergy::mergeStatus($cache['points']))
            ->run();

        $this->assertSame('partial', $res[0]->content['features'][0]['properties']['chargingAvailability']);
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

    /**
     * @return array<string, mixed>
     */
    private function statusPublication(): array
    {
        $decoded = json_decode($this->statusPublicationJson(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function tablePublication(): array
    {
        $decoded = json_decode(
            (string) file_get_contents(__DIR__ . '/fixtures/table-publication.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function statusPublicationJson(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/status-publication.json');
    }
}
