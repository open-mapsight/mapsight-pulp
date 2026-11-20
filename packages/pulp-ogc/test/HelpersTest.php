<?php

declare(strict_types=1);

namespace OpenMapsight\pulpogc\dev\test;

use OpenMapsight\pulpogc\CoordinatePrecision;
use OpenMapsight\pulpogc\Crs;
use OpenMapsight\pulpogc\TextRepair;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
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

    public function testMeasuresDeliveredDecimals(): void
    {
        $collection = ['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'geometry' => ['type' => 'Point', 'coordinates' => [8.1234, 50.4]]],
            ['type' => 'Feature', 'geometry' => ['type' => 'GeometryCollection', 'geometries' => [
                ['type' => 'LineString', 'coordinates' => [[500123, 5600456.06], [1e-3, 2]]],
            ]]],
            ['type' => 'Feature', 'geometry' => null],
        ]];

        self::assertSame(4, CoordinatePrecision::decimals($collection));
        self::assertNull(CoordinatePrecision::decimals(['type' => 'FeatureCollection', 'features' => []]));
        self::assertSame(0, CoordinatePrecision::ofNumber(500123));
        self::assertSame(1, CoordinatePrecision::ofNumber(0.1));
        self::assertSame(15, CoordinatePrecision::ofNumber(8.123456789012345));
    }

    #[DataProvider('texts')]
    public function testRepairsOnlyLosslesslyDoubleEncodedText(string $input, string $expected): void
    {
        self::assertSame($expected, TextRepair::doubleEncodedUtf8($input));
    }

    public static function texts(): array
    {
        return [
            'umlaut' => ["H\u{00C3}\u{00B6}henweg", 'Höhenweg'],
            'sharp s via C1 control' => ["M\u{00C3}\u{00BC}hlenstra\u{00C3}\u{009F}e", 'Mühlenstraße'],
            'already correct' => ['Höhenweg', 'Höhenweg'],
            'ascii' => ['Hoehenweg', 'Hoehenweg'],
            'mixed forms stay' => ["Höhenweg H\u{00C3}\u{00B6}henweg", "Höhenweg H\u{00C3}\u{00B6}henweg"],
            'beyond latin-1 stays' => ["H\u{00C3}\u{00B6}henweg €", "H\u{00C3}\u{00B6}henweg €"],
            'not valid utf-8 after repair' => ["\u{00C3}\u{00B6}\u{00C3}", "\u{00C3}\u{00B6}\u{00C3}"],
            'empty' => ['', ''],
        ];
    }
}
