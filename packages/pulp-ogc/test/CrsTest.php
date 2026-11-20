<?php

declare(strict_types=1);

namespace OpenMapsight\pulpogc\dev\test;

use OpenMapsight\pulpogc\Crs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CrsTest extends TestCase
{
    #[DataProvider('crsNames')]
    public function testNormalizesCrsSpellings(string $name, ?string $expected): void
    {
        self::assertSame($expected, Crs::normalize($name));
    }

    public static function crsNames(): array
    {
        return [
            ['EPSG:25832', 'EPSG:25832'],
            ['epsg:4326', 'EPSG:4326'],
            ['urn:ogc:def:crs:EPSG::25832', 'EPSG:25832'],
            ['urn:ogc:def:crs:EPSG:6.6:4326', 'EPSG:4326'],
            ['urn:x-ogc:def:crs:EPSG:31467', 'EPSG:31467'],
            ['http://www.opengis.net/def/crs/EPSG/0/3857', 'EPSG:3857'],
            ['http://www.opengis.net/gml/srs/epsg.xml#25833', 'EPSG:25833'],
            ['urn:ogc:def:crs:OGC:1.3:CRS84', 'OGC:CRS84'],
            ['http://www.opengis.net/def/crs/OGC/1.3/CRS84', 'OGC:CRS84'],
            ['local:custom', null],
        ];
    }

    public function testComparesCrs(): void
    {
        self::assertTrue(Crs::same('EPSG:25832', 'urn:ogc:def:crs:EPSG::25832'));
        self::assertTrue(Crs::same('EPSG:4326', 'urn:ogc:def:crs:OGC:1.3:CRS84'));
        self::assertFalse(Crs::same('EPSG:25832', 'EPSG:25833'));
        self::assertTrue(Crs::same('local:custom', 'local:custom'));
        self::assertFalse(Crs::same('local:custom', 'EPSG:4326'));
    }
}
