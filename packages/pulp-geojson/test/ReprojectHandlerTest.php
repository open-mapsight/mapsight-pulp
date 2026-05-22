<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\PulpGeoJSON;
use OpenMapsight\PulpJSON;
use PHPUnit\Framework\TestCase;

class ReprojectHandlerTest extends TestCase
{
    public function testXy(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src('input3\.geojson', __DIR__ . '/files'))
            ->pipe(PulpJSON::decodeJSON())
            ->pipe(PulpGeoJSON::reproject('EPSG:900913', ['includeZCoordinate' => false]))
            ->run();

        $this->assertCount(1, $res);
        TestUtils::assertJsonSameFile('expected.reproject.geojson', $res[0]->content);
    }

    public function testXyz(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src('input3\.geojson', __DIR__ . '/files'))
            ->pipe(PulpJSON::decodeJSON())
            ->pipe(PulpGeoJSON::reproject('EPSG:900913'))
            ->run();

        $this->assertCount(1, $res);
        TestUtils::assertJsonSameFile('expected.reproject.xyz.geojson', $res[0]->content);
    }
}
