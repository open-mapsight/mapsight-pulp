<?php

declare(strict_types=1);

namespace OpenMapsight\pulptic;

use DateTime;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class ToGeoJSONHandler extends AbstractHandler
{
    protected static function situationTextToType($text): string
    {
        if ($text) {
            $text = strtolower((string)$text);

            // Descanding in importance:
            if (preg_match('/\bstau\b/', $text) ||
                str_contains($text, 'stockender verkehr')
            ) {
                return 'traffic-jam';
            }

            if (str_contains($text, 'bauarbeiten') ||
                str_contains($text, 'baustelle') ||
                str_contains($text, 'brückenarbeiten')
            ) {
                return 'construction-work';
            }

            if (str_contains($text, 'gesperrt') ||
                str_contains($text, 'sperrung')
            ) {
                return 'closure';
            }

            if (str_contains($text, 'unfall')) {
                return 'accident';
            }
        }

        return 'situation';
    }

    /**
     *
     * @param array $row
     *
     * @return bool
     */
    protected static function isSituationAbolished(array $row)
    {
        if (isset($row['isAbolished'])) {
            return $row['isAbolished'];
        }

        $needles = [
            'störung beseitigt', 'straße geräumt',
            'unfallstelle geräumt', 'wieder frei',
            'verkehr hat sich normalisiert', 'gefahr besteht nicht mehr',
            'störung besteht nicht mehr', 'hindernisse beseitigt',
            'störungsfreier verkehr', 'aufgehoben',
        ];

        $messageText = false;
        if (isset($row['messages']['content'])) {
            $messageText = is_array($row['messages']['content']) ?
                $row['messages']['content'][0] :
                $row['messages']['content'];

            $messageText = mb_strtolower((string)$messageText, 'UTF-8');
        } elseif (isset($row['description'])) {
            $messageText = mb_strtolower($row['description'], 'UTF-8');
        }

        if ($messageText) {
            foreach ($needles as $needle) {
                if (mb_strpos($messageText, $needle, 0, 'UTF-8') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function onFile(File $file): void
    {
        $features = array_map($this->getFeatures(...), $file->content);
        $file->content = [
            'type' => 'FeatureCollection',
            'crs' => ['type' => 'EPSG', 'properties' => ['code' => '4326']],
            'features' => $features,
        ];
        $this->pushFile($file);
    }

    private function xyToGeoJSONCoordinate(array $xy): array
    {
        return [$xy['x'], $xy['y']];
    }

    private function posToGeoJSONCoordinates($pos): array
    {
        return array_map($this->xyToGeoJSONCoordinate(...), $pos);
    }

    private function packageGeometries(array $geometries)
    {
        return match (count($geometries)) {
            0 => null,
            1 => $geometries[0],
            default => [
                'type' => 'GeometryCollection',
                'geometries' => $geometries,
            ],
        };
    }

    private function buildWhen(array $res): ?array
    {
        // when
        $hasStartTime = !empty($res['startTime']);
        $hasStopTime = !empty($res['stopTime']);
        if ($hasStartTime && $hasStopTime) {
            return [
                '@type' => 'Interval',
                'start' => $res['startTime']->format(DateTime::ISO8601),
                'stop' => $res['stopTime']->format(DateTime::ISO8601),
            ];
        }
        if ($hasStartTime) {
            return [
                'start' => $res['startTime']->format(DateTime::ISO8601),
            ];
        }
        if ($hasStopTime) {
            return [
                'stop' => $res['stopTime']->format(DateTime::ISO8601),
            ];
        }

        return null;
    }

    private function buildProperties(array $res)
    {
        // properties
        // copy from $res to $properties
        $properties = $res['messages'] ?? [];
        $properties['type'] = self::situationTextToType($res['description']);
        $properties['isAbolished'] = self::isSituationAbolished($res);
        if (isset($res['activateTime']) && !empty($res['activateTime'])) {
            $properties['updatedAt'] = $res['activateTime']->format(DateTime::ISO8601);
        }
        foreach ([
                     'guid' => 'id',
                     'name' => 'name',
                     'description' => 'description',
                     'originName' => 'originName',
                     'organisation' => 'organisation',
                     'roadNumber' => 'roadNumber',
                     'segment' => 'segment',
                 ] as $source => $target) {
            if (isset($res[$source])) {
                $properties[$target] = $res[$source];
            }
        }

        return $properties;
    }

    private function getFeatures(array $res): array
    {
        $isArea = false;

        // geometry
        $geometries = [];
        foreach ($res['locations'] as $location) {
            switch ($location['type']) {
                case 'area':
                    $geometries[] = [
                        // Big Polygons make the map nearly unusable so we use linestring to display the contour instead
                        //'type' => 'Polygon',
                        //'coordinates' => array(self::posToGeoJSONCoordinates($location['pos']))
                        'type' => 'LineString',
                        'coordinates' => $this->posToGeoJSONCoordinates($location['pos']),
                    ];
                    $isArea = true;
                    break;
                case 'line':
                    $geometries[] = [
                        'type' => 'LineString',
                        'coordinates' => $this->posToGeoJSONCoordinates($location['pos']),
                    ];
                    break;
                case 'point':
                    if (isset($location['pos'][0])) {
                        $geometries[] = [
                            'type' => 'Point',
                            'coordinates' => $this->xyToGeoJSONCoordinate($location['pos'][0]),
                        ];
                    }
                    break;
                default:
            }
        }

        // in a second loop (so they are ordered at the end) add points to lines
        foreach ($res['locations'] as $location) {
            switch ($location['type']) {
                case 'line':
                    if (isset($location['pos'][0])) {
                        $geometries[] = [
                            'type' => 'Point',
                            'coordinates' => $this->xyToGeoJSONCoordinate($location['pos'][0]),
                        ];
                    }
                    break;
                default:
            }
        }

        $properties = $this->buildProperties($res);

        if ($isArea) {
            $properties['isArea'] = true;
        }

        // combine
        $result = [
            'type' => 'Feature',
            'id' => $res['guid'],
            'properties' => $properties,
            'geometry' => $this->packageGeometries($geometries),
        ];

        $when = $this->buildWhen($res);
        if ($when !== null && $when !== []) {
            $result['when'] = $when;
        }

        return $result;
    }
}
