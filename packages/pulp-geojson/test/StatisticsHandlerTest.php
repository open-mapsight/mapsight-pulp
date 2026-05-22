<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson\dev\test;

use Exception;
use OpenMapsight\Pulp;
use OpenMapsight\PulpGeoJSON;
use OpenMapsight\PulpJSON;
use PHPUnit\Framework\TestCase;

class StatisticsHandlerTest extends TestCase
{
    public function test(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src('input1\.geojson', __DIR__ . '/files'))
            ->pipe(PulpJSON::decodeJSON())
            ->pipe(PulpGeoJSON::addStatistics('statistics'))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame(
            [
                'featureCount' => 3,
            ],
            $res[0]->content['statistics']
        );
    }

    public function testWithPath(): void
    {
        $res = Pulp::start()
            ->pipe(Pulp::src('input1\.geojson', __DIR__ . '/files'))
            ->pipe(PulpJSON::decodeJSON())
            ->pipe(PulpGeoJSON::addStatistics(['statistics', 'sub1', 'sub2']))
            ->run();

        $this->assertCount(1, $res);
        $this->assertSame(
            [
                'sub1' => [
                    'sub2' => [
                        'featureCount' => 3,
                    ],
                ],
            ],
            $res[0]->content['statistics']
        );
    }

    public function testKeyExistsButNotHash(): void
    {
        $exception = null;
        try {
            Pulp::start()
                ->pipe(Pulp::src('input2\.geojson', __DIR__ . '/files'))
                ->pipe(PulpJSON::decodeJSON())
                ->pipe(PulpGeoJSON::addStatistics('statistics'))
                ->run();
        } catch (Exception $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception);
        $this->assertSame(
            'key "statistics" exists, but is not an array',
            $exception->getMessage()
        );
    }
}
