<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use OpenMapsight\GeoJsonReproject;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class ToArcGisJsonHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['idAttribute'];
    }

    public function onFile(File $file): void
    {
        Utils::assertGeoJSONFile($file);

        /** @var array $data */
        $data = $file->content;

        $file->content = self::geoJsonToArcGisJson($data, $this->cp->idAttribute);
        $this->pushFile($file);
    }

    protected static function geoJsonToArcGisJson(array $geojson, string $idAttribute): array
    {
        $spatialReference = str_replace('EPSG:', '', GeoJsonReproject::getProjection($geojson) ?? 'EPSG:4326');
        return match ($geojson['type']) {
            'Point' => [
                'x' => $geojson['coordinates'][0],
                'y' => $geojson['coordinates'][1],
                'spatialReference' => $spatialReference,
            ],
            'MultiPoint' => [
                'points' => $geojson['coordinates'],
                'spatialReference' => $spatialReference,
            ],
            'LineString' => [
                'paths' => [$geojson['coordinates']],
                'spatialReference' => $spatialReference,
            ],
            'MultiLineString' => [
                'paths' => $geojson['coordinates'],
                'spatialReference' => $spatialReference,
            ],
            'Polygon' => [
                'rings' => ArcGisUtils::orientRings($geojson['coordinates']),
                'spatialReference' => $spatialReference,
            ],
            'MultiPolygon' => [
                'rings' => ArcGisUtils::flattenMultiPolygonRings($geojson['coordinates']),
                'spatialReference' => $spatialReference,
            ],
            'Feature' => [
                'geometry' => isset($geojson['geometry']) ? self::geoJsonToArcGisJson($geojson['geometry'], $idAttribute) : null,
                'attributes' => $geojson['properties'] ?? null,
                $idAttribute => $geojson['id'] ?? null,
            ],
            'FeatureCollection' => array_map(static fn(array $feature): array => self::geoJsonToArcGisJson($feature, $idAttribute), $geojson['features']),
            'GeometryCollection' => array_map(static fn(array $geometry): array => self::geoJsonToArcGisJson($geometry, $idAttribute), $geojson['geometries']),
            default => [],
        };
    }
}
