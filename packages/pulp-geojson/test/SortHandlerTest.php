<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\PulpGeoJSON;
use OpenMapsight\PulpJSON;
use PHPUnit\Framework\TestCase;

class SortHandlerTest extends TestCase
{
    public function test(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src('input1\.geojson', __DIR__ . '/files'))
            ->pipe(PulpJSON::decodeJSON())
            ->pipe(PulpGeoJSON::sort(static fn($a, $b): int => strcmp((string) $b['geometry']['type'], (string) $a['geometry']['type'])))
            ->run();

        $this->assertCount(1, $res);
        TestUtils::assertJsonSameFile('expected.sort.geojson', $res[0]->content);
    }
}
