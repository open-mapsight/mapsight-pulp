<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;

use function fwrite;

use Geometry;
use geoPHP;

use function json_encode;

use OpenMapsight\GeoJsonReproject;
use OpenMapsight\pulp\File;
use RuntimeException;

use const STDERR;

use Throwable;

// workaround to get geophp classes loaded (`\Geometry`)
geoPHP::version();

class Utils
{
    /**
     * @throws Exception
     */
    public static function extractFeatures(array $data): array
    {
        return match ($data['type']) {
            'FeatureCollection' => $data['features'],
            'Feature' => [$data],
            default => throw new RuntimeException('Unrecognized GeoJSON type: ' . $data['type']),
        };
    }

    public static function assertGeoJSONFile(File $file): void
    {
        if (!is_array($file->content)) {
            throw new RuntimeException('Not an JSON file in "' . $file->fileName . '"');
        }

        $data = $file->content;

        if (empty($data['type'])) {
            throw new RuntimeException('Undefined type in ' . $file->fileName);
        }

        $allowedTypes = ['FeatureCollection', 'Feature'];
        if (!in_array($data['type'], $allowedTypes, true)) {
            throw new RuntimeException(
                'Unsupported root element type("' . $data['type'] .
                '") in "' . $file->fileName . '"'
            );
        }

    }

    public static function assertFile(File $file): void
    {
        self::assertGeoJSONFile($file);

        /** @var array $data */
        $data = $file->content;

        foreach ($data['features'] as $f) {
            if (!isset($f['type'])) {
                throw new RuntimeException('Element has no type in "' . $file->fileName . '"');
            }

            if ($f['type'] !== 'Feature') {
                throw new RuntimeException('Unsupported element type("' . $f['type'] .
                    '"; expected "Feature")' . 'in "' . $file->fileName . '"');
            }
        }
    }

    public static function mapFilterFeatures(array $data, $cb, bool $skipExceptions = false): array
    {
        $ret = [];
        foreach ($data['features'] as $f) {
            try {
                $curRes = $cb($f);
                if ($curRes !== null) {
                    $ret[] = $curRes;
                }
            } catch (Throwable $e) {
                if ($skipExceptions) {
                    $ret[] = self::createErrorFeature($e);
                } else {
                    throw $e;
                }
            }
        }
        $data['features'] = $ret;

        return $data;
    }

    public static function isErrorFeature(array $feat): bool
    {
        return isset($feat['properties']['isError']) && $feat['properties']['isError'] === true;
    }

    public static function getErrorInfoFromFeature(array $feat): ?array
    {
        if (!self::isErrorFeature($feat)) {
            return null;
        }

        $props = $feat['properties'];
        return [
            'class' => $props['errorClass'],
            'message' => $props['errorMessage'],
            'file' => $props['errorFile'],
            'line' => $props['errorLine'],
        ];
    }

    public static function createErrorFeature(Throwable $err): array
    {
        return [
            'type' => 'Feature',
            'geometry' => null,
            'properties' => [
                'name' => 'Error: ' . $err->getMessage(),
                'isError' => true,
                'errorClass' => $err::class,
                'errorMessage' => $err->getMessage(),
                'errorFile' => $err->getFile(),
                'errorLine' => $err->getLine(),
            ],
        ];
    }

    public static function createErrorFileContent(Throwable $err): array
    {
        return GeoJsonReproject::setProjection(
            [
                'type' => 'FeatureCollection',
                'features' => [
                    self::createErrorFeature($err),
                ],
            ],
            'EPSG:4326'
        );
    }

    private static function _mapFilterGeomRecursive(?array $g, callable $cb, bool $skipExceptions): ?array
    {
        if ($g === null) {
            return null;
        }

        if ($g['type'] === 'GeometryCollection') {
            $ret = [];
            foreach ($g['geometries'] as $g2) {
                $curRes = self::_mapFilterGeomRecursive(
                    $g2,
                    $cb,
                    $skipExceptions
                );

                if (is_array($curRes) && $curRes !== []) {
                    $ret[] = $curRes;
                }
            }
            if ($ret === []) {
                return null;
            }

            if (!isset($ret[1])) {
                return $ret[0];
            }

            $g['geometries'] = $ret;

            return $g;
        }

        try {
            return $cb($g);
        } catch (Throwable $e) {
            if ($skipExceptions) {
                fwrite(
                    STDERR,
                    'Skipped geojson geometry due exception in filter or map to:' . "\n"
                    . $e . "\n"
                );
            } else {
                throw $e;
            }
        }

        return null;
    }

    public static function mapFilterGeometry(array $data, callable $cb, bool $skipExceptions = false): array
    {
        return self::mapFilterFeatures(
            $data,
            static function (array $f) use ($cb, $skipExceptions): array {
                $f['geometry'] = self::_mapFilterGeomRecursive(
                    $f['geometry'],
                    $cb,
                    $skipExceptions
                );

                return $f;
            }
        );
    }

    public static function mapFilterGeometryFromFeature(array $feature, callable $cb, bool $skipExceptions = false): array
    {
        $feature['geometry'] = self::_mapFilterGeomRecursive(
            $feature['geometry'],
            $cb,
            $skipExceptions
        );

        return $feature;
    }

    public static function getGeometryForFeature(array $feature): ?Geometry
    {
        try {
            if (empty($feature['geometry'])) {
                return null;
            }
            // `geoPHP::load` takes only a string or an json-object, but we're working with
            // json-(maps)arrays, so we need to json-encode the geo into a string
            return geoPHP::load(json_encode($feature['geometry']), 'json');
        } catch (Exception $e) {
            fwrite(STDERR, 'Error parsing geometry of a GeoJson feature: ');
            fwrite(STDERR, $e);
            return null;
        }
    }
}
