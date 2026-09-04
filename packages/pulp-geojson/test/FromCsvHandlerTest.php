<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpGeoJSON;
use PHPUnit\Framework\TestCase;

class FromCsvHandlerTest extends TestCase
{
    public function testMapsEveryRowWhenThereAreMoreRowsThanColumns(): void
    {
        $file = new File('stations.csv');
        $file->content = "name;ort\nAlpha;Braunschweig\nBeta;Braunschweig\nGamma;Hannover\n";

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpGeoJSON::fromCsv())
            ->run();

        $names = array_map(
            static fn(array $feature): string => (string) ($feature['properties']['name'] ?? ''),
            $res[0]->content['features']
        );
        $this->assertSame(['Alpha', 'Beta', 'Gamma'], $names);
    }

    public function testKeepsUtf8Umlauts(): void
    {
        $file = new File('streets.csv');
        $file->content = "name;ort\nOkerinsel;Straße\n";

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpGeoJSON::fromCsv())
            ->run();

        $this->assertSame(
            'Straße',
            $res[0]->content['features'][0]['properties']['ort']
        );
    }

    public function testMapsRowWhenThereAreMoreColumnsThanRows(): void
    {
        $file = new File('one.csv');
        $file->content = "name;a;b;c;d\nonly;;;;\n";

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpGeoJSON::fromCsv())
            ->run();

        $this->assertCount(1, $res[0]->content['features']);
        $this->assertSame('only', $res[0]->content['features'][0]['properties']['name']);
    }

    public function testTrailingEmptyLineDoesNotFail(): void
    {
        $file = new File('stations.csv');
        $file->content = "name;ort\nAlpha;Braunschweig\n";

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpGeoJSON::fromCsv())
            ->pipe(PulpGeoJSON::merge('stations.geojson'))
            ->run();

        $this->assertCount(1, $res[0]->content['features']);
        $this->assertSame('Alpha', $res[0]->content['features'][0]['properties']['name']);
    }
}
