<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpGeoJSON;
use OpenMapsight\PulpJSON;
use PHPUnit\Framework\TestCase;

class MergeHandlerTest extends TestCase
{
    public function test(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src('input1\.geojson', __DIR__ . '/files'))
            ->pipe(Pulp::src('input2\.geojson', __DIR__ . '/files'))
            ->pipe(Pulp::results(function ($res): void {
                $this->assertCount(2, $res);
            }))
            ->pipe(PulpJSON::decodeJSON())
            ->pipe(PulpGeoJSON::merge(
                'output.geojson',
                ['includeSourceInformation' => true]
            ))
            ->run();

        $this->assertCount(1, $res);
        TestUtils::assertJsonSameFile('expected.merge.geojson', $res[0]->content);
    }

    public function testMergeWithoutCrsDoesNotTypeError(): void
    {
        $file = new File('points.geojson');
        $file->content = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [10.5, 52.2]],
                    'properties' => ['name' => 'A'],
                ],
            ],
        ];

        $res = Pulp::start()
            ->pipe(Pulp::src($file))
            ->pipe(PulpGeoJSON::merge('output.geojson'))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame('FeatureCollection', $res[0]->content['type']);
        $this->assertCount(1, $res[0]->content['features']);
        $this->assertSame('A', $res[0]->content['features'][0]['properties']['name']);
    }
}
