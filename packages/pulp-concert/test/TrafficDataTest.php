<?php

declare(strict_types=1);

namespace OpenMapsight\pulpconcert\dev\test;

define('DEBUG', true);

use OpenMapsight\Pulp;
use OpenMapsight\PulpConcert;
use OpenMapsight\PulpJSON;
use OpenMapsight\PulpXML;
use PHPUnit\Framework\TestCase;

class TrafficDataTest extends TestCase
{
    protected function setUp(): void
    {
        date_default_timezone_set('Europe/Berlin');
    }

    public function test(): void
    {
        $counterData = Pulp::start()
            ->pipe(Pulp::src('counter-data\.xml', __DIR__ . '/files/TrafficData/'))
            ->pipe(PulpXML::stripXmlNamespaces())
            ->pipe(PulpXML::parseSimpleXML());

        $losTables = Pulp::start()
            ->pipe(Pulp::src('.*\.xml', __DIR__ . '/files/TrafficData/los-tables/'))
            ->pipe(PulpXML::stripXmlNamespaces())
            ->pipe(PulpXML::parseSimpleXML())
            ->pipe(PulpConcert::parseTrafficLevelOfServiceTable());

        $counterCombined = Pulp::start()
            ->pipe(Pulp::src('sub-section-descriptions\.xml', __DIR__ . '/files/TrafficData/'))
            ->pipe(PulpXML::stripXmlNamespaces())
            ->pipe(PulpXML::parseSimpleXML())
            ->pipe(PulpConcert::parseTrafficCounters($counterData));

        $res = Pulp::start()
            ->pipe(Pulp::src('sub-sections\.geojson', __DIR__ . '/files/TrafficData/'))
            ->pipe(PulpJSON::decodeJSON())
            ->pipe(PulpConcert::mergeTrafficCountersIntoGeoJSON($counterCombined, $losTables))
            ->run();

        $this->assertCount(1, $res);

        // takes a new snapshot
        // file_put_contents(
        //     __DIR__ . '/files/TrafficData/expected.traffic.geojson',
        //     json_encode($res[0]->content, JSON_PRETTY_PRINT)
        // );

        TestUtils::assertJsonSameFile(
            'TrafficData/expected.traffic.geojson',
            $res[0]->content
        );
    }
}
