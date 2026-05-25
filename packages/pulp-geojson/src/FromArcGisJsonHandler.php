<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use function json_encode;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use RuntimeException;

class FromArcGisJsonHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['idAttribute'];
    }

    public function onFile(File $file): void
    {
        if (!is_array($file->content)) {
            throw new RuntimeException('Not an JSON file in "' . $file->fileName . '"');
        }

        $data = $file->content;

        $file->content = self::arcGisJsonToGeoJson($data, $this->cp->idAttribute);
        $this->pushFile($file);
    }

    protected static function arcGisJsonToGeoJson(array $element, ?string $idAttribute = null): array
    {
        $geojson = [];
        if (isset($element['features']) && is_array($element['features'])) {
            $geojson = [
                'type' => 'FeatureCollection',
                'features' => array_map(static fn(array $feature): array => self::arcGisJsonToGeoJson($feature, $idAttribute), $element['features']),
            ];
        } elseif (isset($element['x'], $element['y']) && is_numeric($element['x']) && is_numeric($element['y'])) {
            $geojson = [
                'type' => 'Point',
                'coordinates' => array_filter([$element['x'], $element['y'], $element['z'] ?? null]),
            ];
        } elseif (isset($element['points']) && is_array($element['points'])) {
            $geojson = [
                'type' => 'MultiPoint',
                'coordinates' => $element['points'],
            ];
        } elseif (isset($element['paths']) && is_array($element['paths'])) {
            if (count($element['paths']) === 1) {
                $geojson = [
                    'type' => 'LineString',
                    'coordinates' => $element['paths'][0],
                ];
            } else {
                $geojson = [
                    'type' => 'MultiLineString',
                    'coordinates' => $element['paths'],
                ];
            }
        } elseif (isset($element['rings']) && is_array($element['rings'])) {
            $geojson = ArcGisUtils::convertRingsToGeoJSON($element['rings']);
        } elseif (isset($element['xmin'], $element['ymin'], $element['xmax'], $element['ymax']) &&
        is_numeric($element['xmin']) &&
        is_numeric($element['ymin']) &&
        is_numeric($element['xmax']) &&
        is_numeric($element['ymax'])) {
            $geojson = [
                'type' => 'Polygon',
                'coordinates' => [[
                    [$element['xmax'], $element['ymax']],
                    [$element['xmin'], $element['ymax']],
                    [$element['xmin'], $element['ymin']],
                    [$element['xmax'], $element['ymin']],
                    [$element['xmax'], $element['ymax']],
                ]],
            ];
        } elseif (isset($element['geometry']) || isset($element['attributes'])) {
            $geojson = [
                'type' => 'Feature',
                'geometry' => isset($element['geometry']) ? self::arcGisJsonToGeoJson($element['geometry']) : null,
                'properties' => $element['attributes'] ?? null,
            ];
            // handle ids
            if (isset($element['attributes'])) {
                try {
                    $id = ArcGisUtils::getId($element['attributes'], $idAttribute);
                    $geojson['id'] = $id;
                    $geojson['properties']['id'] = $id;
                } catch (RuntimeException) {
                    // TODO: Handle missing ids?
                }
            }
        }


        // if no valid geometry was encountered, null it
        if (isset($geojson['geometry']) && json_encode($geojson['geometry']) === '{}') {
            $geojson['geometry'] = null;
        }

        // handle crs
        if (isset($element['spatialReference']['wkid'])) {
            $crs = 'EPSG:' . $element['spatialReference']['wkid'];
            $geojson['crs'] = ['type' => 'name', 'properties' => ['name' => $crs]];
        }

        return $geojson;
    }
}
