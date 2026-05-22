<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\PulpGeoJSON;
use OpenMapsight\PulpJSON;
use PHPUnit\Framework\TestCase;

class MapHandlerTest extends TestCase
{
    public function test(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src('input1\.geojson', __DIR__ . '/files'))
            ->pipe(PulpJSON::decodeJSON())
            ->pipe(PulpGeoJSON::map(static function (array $f): array {
                $f['properties']['geometryType'] = $f['geometry']['type'];

                return $f;
            }))
            ->run();

        $this->assertCount(1, $res);
        TestUtils::assertJsonSameFile('expected.map.geojson', $res[0]->content);
    }
}
