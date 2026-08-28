<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexenergy\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpDatexEnergy;
use OpenMapsight\pulpdatexenergy\DatexEnergySitesBuilder;
use PHPUnit\Framework\TestCase;

class DatexEnergySitesBuilderTest extends TestCase
{
    private const BBOX = [10.42, 52.18, 10.65, 52.36];

    public function testBuildEmitsOneFeaturePerSiteInBbox(): void
    {
        $geoJson = $this->createBuilder()->build($this->tablePublication());

        $this->assertSame('FeatureCollection', $geoJson['type']);
        $this->assertSame([
            'name' => 'DATEX Energy',
            'url' => 'https://public.example/datex',
            'documentationUrl' => 'https://docs.example/datex',
            'bbox' => self::BBOX,
        ], $geoJson['source']);
        $this->assertCount(2, $geoJson['features']);

        $ac = $this->featureById($geoJson, 'datex-energy-site-site-ac-in-bbox');
        $this->assertSame('Point', $ac['geometry']['type']);
        $this->assertEqualsWithDelta([10.510378, 52.309392], $ac['geometry']['coordinates'], 0.000001);
        $this->assertSame('site-ac-in-bbox', $ac['properties']['siteId']);
        $this->assertSame('B&B Braunschweig Nord', $ac['properties']['name']);
        $this->assertSame('Example Operator', $ac['properties']['operator']);
        $this->assertSame('Hansestrasse 90A', $ac['properties']['addressLine']);
        $this->assertSame('38112', $ac['properties']['postcode']);
        $this->assertSame('Braunschweig', $ac['properties']['city']);
        $this->assertSame('DE', $ac['properties']['countryCode']);
        $this->assertSame(['DE*MUC*E*CO110897', 'DE*MUC*E*CO110898'], $ac['properties']['evseIds']);
        $this->assertSame(['iec62196T2'], $ac['properties']['connectorTypes']);
        $this->assertSame(['ac'], $ac['properties']['currentTypes']);
        $this->assertSame(11000, $ac['properties']['maxPowerWatts']);
        $this->assertSame('DATEX Energy', $ac['properties']['source']);
        $this->assertSame('https://public.example/datex', $ac['properties']['sourceUrl']);
        $this->assertArrayNotHasKey('mapsightIconId', $ac['properties']);
        $this->assertArrayNotHasKey('description', $ac['properties']);
        $this->assertArrayNotHasKey('tagGroups', $ac['properties']);
        $this->assertArrayNotHasKey('listInformation', $ac['properties']);

        $dc = $this->featureById($geoJson, 'datex-energy-site-9619ab5b-967f-4ad2-9d10-ee6be49cabce');
        $this->assertSame('BS|ENERGY Petritorwall', $dc['properties']['name']);
        $this->assertSame(['DE*LDK*E00122', 'DE*LDK*E00121'], $dc['properties']['evseIds']);
        $this->assertSame(['iec62196T2COMBO', 'iec62196T2'], $dc['properties']['connectorTypes']);
        $this->assertSame(['dc', 'ac'], $dc['properties']['currentTypes']);
        $this->assertSame(50000, $dc['properties']['maxPowerWatts']);
        $this->assertSame('dc', $dc['properties']['chargingPoints'][0]['currentType']);
        $this->assertSame(['iec62196T2COMBO'], $dc['properties']['chargingPoints'][0]['connectorTypes']);
    }

    public function testPreferredLangSelectsLocalizedName(): void
    {
        $geoJson = $this->createBuilder(preferredLang: 'en')->build($this->tablePublication());

        $ac = $this->featureById($geoJson, 'datex-energy-site-site-ac-in-bbox');
        $this->assertSame('B&B Brunswick North', $ac['properties']['name']);
    }

    public function testSitesInBboxDropsOutsideZeroAndMissingGeometry(): void
    {
        $sites = $this->createBuilder()->sitesInBbox($this->tablePublication());
        $ids = array_map(static fn(array $site): string => (string) $site['idG'], $sites);

        $this->assertSame(['site-ac-in-bbox', '9619ab5b-967f-4ad2-9d10-ee6be49cabce', 'site-no-points'], $ids);
    }

    public function testFeatureFromSiteSkipsSitesWithoutChargingPoints(): void
    {
        $site = null;
        foreach ($this->createBuilder()->sitesInBbox($this->tablePublication()) as $candidate) {
            if ($candidate['idG'] === 'site-no-points') {
                $site = $candidate;
            }
        }

        $this->assertNotNull($site);
        $this->assertNull($this->createBuilder()->featureFromSite($site));
    }

    public function testBuildAcceptsUnwrappedTablePublication(): void
    {
        $publication = $this->tablePublication();
        $unwrapped = $publication['payload'];

        $geoJson = $this->createBuilder()->build($unwrapped);

        $this->assertCount(2, $geoJson['features']);
    }

    public function testGeoJsonHandlerConsumesDecodedPublication(): void
    {
        $file = new File('table.json');
        $file->content = $this->tablePublication();

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpDatexEnergy::sitesGeoJson(
                self::BBOX,
                'https://internal.example/datex',
                'DATEX Energy',
                'https://docs.example/datex',
                'https://public.example/datex'
            ))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('datex-energy-sites.geojson', $res[0]->fileName);
        $this->assertSame('FeatureCollection', $res[0]->content['type']);
        $this->assertCount(2, $res[0]->content['features']);
    }

    public function testGeoJsonHandlerDecodesJsonString(): void
    {
        $file = new File('table.json');
        $file->content = $this->tablePublicationJson();

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpDatexEnergy::sitesGeoJson(self::BBOX))
            ->run();

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

    private function createBuilder(?string $preferredLang = null): DatexEnergySitesBuilder
    {
        return PulpDatexEnergy::sitesBuilder(
            self::BBOX,
            'https://internal.example/datex',
            'DATEX Energy',
            'https://docs.example/datex',
            'https://public.example/datex',
            $preferredLang === null ? [] : ['preferredLang' => $preferredLang]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function tablePublication(): array
    {
        $decoded = json_decode($this->tablePublicationJson(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function tablePublicationJson(): string
    {
        return (string) file_get_contents(__DIR__ . '/fixtures/table-publication.json');
    }
}
