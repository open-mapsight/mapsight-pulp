<?php

declare(strict_types=1);

use OpenMapsight\GeoJsonReproject;
use PHPUnit\Framework\TestCase;

class GeoJsonReprojectTest extends TestCase
{
    public function testProjectionHelpers(): void
    {
        $data = GeoJsonReproject::setProjection([
            'type' => 'FeatureCollection',
            'features' => [],
        ], 'EPSG:3857');

        $this->assertSame('EPSG:3857', GeoJsonReproject::getProjection($data));
        $this->assertNull(GeoJsonReproject::getProjection([
            'type' => 'FeatureCollection',
            'features' => [],
        ]));
    }

    public function testReprojectFeatureCollectionUpdatesProjectionAndCoordinates(): void
    {
        $geoJson = [
            'type' => 'FeatureCollection',
            'crs' => [
                'type' => 'name',
                'properties' => [
                    'name' => 'EPSG:4326',
                ],
            ],
            'features' => [
                [
                    'type' => 'Feature',
                    'properties' => [
                        'name' => 'Berlin',
                    ],
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [13.4, 52.5, 7],
                    ],
                ],
            ],
        ];

        $result = GeoJsonReproject::reproject($geoJson, 'EPSG:4326', [
            'includeZCoordinate' => true,
        ]);

        $this->assertSame('EPSG:4326', GeoJsonReproject::getProjection($result));
        $this->assertSame('Berlin', $result['features'][0]['properties']['name']);
        $this->assertEqualsWithDelta(13.4, $result['features'][0]['geometry']['coordinates'][0], 0.000001);
        $this->assertEqualsWithDelta(52.5, $result['features'][0]['geometry']['coordinates'][1], 0.000001);
        $this->assertEqualsWithDelta(7, $result['features'][0]['geometry']['coordinates'][2], 0.000001);
    }
}
