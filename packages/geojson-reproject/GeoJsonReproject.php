<?php

declare(strict_types=1);

namespace OpenMapsight;

use proj4php\Point;
use proj4php\Proj;
use proj4php\Proj4php;

class GeoJsonReproject
{
    private static ?Proj4php $proj4php = null;

    private static function getProj4Php(): ?Proj4php
    {
        if (is_null(self::$proj4php)) {
            self::$proj4php = new Proj4php();
        }

        return self::$proj4php;
    }

    public static function getProjection(array $data): ?string
    {
        if (!isset($data['crs'])) {
            return null;
        }

        if ($data['crs']['type'] === 'name' && isset($data['crs']['properties']['name'])) {
            return $data['crs']['properties']['name'];
        }

        if (isset($data['crs']['type']) && isset($data['crs']['properties']['code'])) {
            return $data['crs']['type'] . ':' . $data['crs']['properties']['code'];
        }

        return null;
    }

    /**
     * @param array  $data
     * @param string $projection
     *
     * @return array
     */
    public static function setProjection(array $data, string $projection): array
    {
        $data['crs'] = [
            'type' => 'name',
            'properties' => [
                'name' => $projection,
            ],
        ];

        return $data;
    }

    /**
     * @param mixed  $data
     * @param string $destinationProjection
     * @param array  $options
     *
     * @return mixed reprojected data
     */
    public static function reproject(mixed $data, string $destinationProjection = 'EPSG:4326', array $options = []): mixed
    {
        $options['destProj'] = $destinationProjection;
        $srcProj = $options['srcProj'] ?? 'EPSG:4326';
        $data = self::reprojectData($data, $srcProj, $options);
        $data = self::setProjection($data, $destinationProjection);

        return $data;
    }

    /**
     * @param mixed  $data
     * @param string $sourceProjection
     * @param array  $options
     *
     * @return mixed reprojected data
     */
    private static function reprojectData(mixed $data, string $sourceProjection, array $options): mixed
    {
        if ($data === null) {
            return null;
        }

        $fileProjection = self::getProjection($data);
        if ($fileProjection !== null) {
            $sourceProjection = $fileProjection;
        }

        if (isset($data['crs'])) {
            unset($data['crs']);
        }

        $type = $data['type'] ?? null;
        switch ($type) {
            case 'FeatureCollection':
                $data['features'] = array_map(fn($feature): mixed => self::reprojectData($feature, $sourceProjection, $options), $data['features']);
                break;

            case 'Feature':
                $data['geometry'] = self::reprojectData($data['geometry'], $sourceProjection, $options);
                break;

            case 'GeometryCollection':
                $data['geometries'] = array_map(fn($geometry): mixed => self::reprojectData($geometry, $sourceProjection, $options), $data['geometries']);
                break;

            case 'MultiPolygon':
                $data['coordinates'] = array_map(fn($polygonArray): array => array_map(fn($linearString): mixed => self::reprojectData($linearString, $sourceProjection, $options), $polygonArray), $data['coordinates']);
                break;

            case 'Polygon':
            case 'MultiLineString':
                $data['coordinates'] = array_map(fn($linearString): mixed => self::reprojectData($linearString, $sourceProjection, $options), $data['coordinates']);
                break;

            case 'Point':
                $data['coordinates'] = self::reprojectCoordinates($sourceProjection, $data['coordinates'], $options);
                break;

            case 'LineString':
            case 'MultiPoint':
                $data['coordinates'] = array_map(fn(array $coordinate): array => self::reprojectCoordinates($sourceProjection, $coordinate, $options), $data['coordinates']);
                break;

            default:
                $isPoint = !is_array($data[0]);

                if ($isPoint) {
                    $data = self::reprojectCoordinates($sourceProjection, $data, $options);
                } else {
                    $data = array_map(fn(array $coordinate): array => self::reprojectCoordinates($sourceProjection, $coordinate, $options), $data);
                }
        }

        return $data;
    }

    /**
     * @param number[] $coordinates
     * @param string   $sourceProjection
     * @param string   $destinationProjection
     *
     * @return Point
     */
    private static function transformCoordinates(array $coordinates, string $sourceProjection, string $destinationProjection): Point
    {
        $proj4php = self::getProj4Php();

        return $proj4php->transform(
            new Proj($sourceProjection, $proj4php),
            new Proj($destinationProjection, $proj4php),
            new Point($coordinates[0], $coordinates[1], $coordinates[2])
        );
    }

    /**
     * @param string   $sourceProjection
     * @param number[] $coordinates
     * @param array    $options
     *
     * @return array reprojected coordinates
     */
    private static function reprojectCoordinates(string $sourceProjection, array $coordinates, array $options): array
    {
        $coordinates[2] ??= null;
        $point = self::transformCoordinates($coordinates, $sourceProjection, $options['destProj']);
        $newCoordinates = [$point->x, $point->y];

        if (isset($options['includeZCoordinate']) && $options['includeZCoordinate'] === true) {
            $newCoordinates[2] = $point->z;
        }

        if (isset($options['useIntegerCoordinates']) && $options['useIntegerCoordinates'] === true) {
            return array_map(intval(...), $newCoordinates);
        }

        return $newCoordinates;
    }
}
