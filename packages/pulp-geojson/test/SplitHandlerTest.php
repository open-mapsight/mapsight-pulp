<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\PulpGeoJSON;
use OpenMapsight\PulpJSON;
use PHPUnit\Framework\TestCase;

class SplitHandlerTest extends TestCase
{
    public function test(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src('input1\.geojson', __DIR__ . '/files'))
            ->pipe(PulpJSON::decodeJSON())
            ->pipe(PulpGeoJSON::split(static fn($f): string => $f['geometry']['type'] . '.geojson'))
            ->run();

        $this->assertCount(3, $res);

        $this->assertSame('Point.geojson', $res[0]->fileName);
        TestUtils::assertJsonSameFile(
            'expected.split-Point.geojson',
            $res[0]->content
        );

        $this->assertSame('LineString.geojson', $res[1]->fileName);
        TestUtils::assertJsonSameFile(
            'expected.split-LineString.geojson',
            $res[1]->content
        );

        $this->assertSame('Polygon.geojson', $res[2]->fileName);
        TestUtils::assertJsonSameFile(
            'expected.split-Polygon.geojson',
            $res[2]->content
        );
    }
}
