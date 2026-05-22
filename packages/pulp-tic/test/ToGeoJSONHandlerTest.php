<?php

declare(strict_types=1);

namespace OpenMapsight\pulptic\dev\test;

use OpenMapsight\Pulp;
use OpenMapsight\PulpJSON;
use OpenMapsight\PulpTIC;
use PHPUnit_Framework_TestCase;

class ToGeoJSONHandlerTest extends PHPUnit_Framework_TestCase
{
    public function test(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::srcFile(__DIR__ . '/files/input.xml', 'input.geojson'))
            ->pipe(PulpTIC::parse())
            ->pipe(PulpTIC::toGeoJSON())
            ->pipe(PulpJSON::encodeJSON(JSON_PRETTY_PRINT))
            ->pipe(Pulp::dest(__DIR__ . '/files/tmp/'))
            ->run();
        TestUtils::assertJsonSameFile('expected.togeojson.json', json_decode((string) $res[0]->content, JSON_OBJECT_AS_ARRAY));
    }

    public function testTic2(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::srcFile(__DIR__ . '/files/input-tic2.xml', 'input-tic2.geojson'))
            ->pipe(PulpTIC::parse())
            ->pipe(PulpTIC::toGeoJSON())
            ->pipe(PulpJSON::encodeJSON(JSON_PRETTY_PRINT))
            ->pipe(Pulp::dest(__DIR__ . '/files/tmp/'))
            ->run();
        TestUtils::assertJsonSameFile('expected-tic2.togeojson.json', json_decode((string) $res[0]->content, JSON_OBJECT_AS_ARRAY));
    }

    public function testTic3(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::srcFile(__DIR__ . '/files/input-tic3.xml', 'input-tic3.geojson'))
            ->pipe(PulpTIC::parse())
            ->pipe(PulpTIC::toGeoJSON())
            ->pipe(PulpJSON::encodeJSON(JSON_PRETTY_PRINT))
            ->pipe(Pulp::dest(__DIR__ . '/files/tmp/'))
            ->run();
        TestUtils::assertJsonSameFile('expected-tic3.togeojson.json', json_decode((string) $res[0]->content, JSON_OBJECT_AS_ARRAY));
    }
}
