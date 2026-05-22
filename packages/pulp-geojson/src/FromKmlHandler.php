<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

// use geoPHP;
// use OpenMapsight\GeoJsonReproject;
use Exception;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use StepanDalecky\KmlParser\Entities\Folder;
use StepanDalecky\KmlParser\Entities\Placemark;
use StepanDalecky\KmlParser\Parser as KmlParser;

class FromKmlHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return [];
    }

    public function onFile(File $file): void
    {
        $fnInfo = pathinfo($file->fileName);

        $kmlData = (static function () use ($fnInfo, $file) {
            switch ($fnInfo['extension']) {
                case 'kml':
                    return $file->content;

                case 'kmz':
                    $tmpFn = tempnam(sys_get_temp_dir(), 'kmz') . '.zip';
                    try {
                        file_put_contents($tmpFn, $file->content);
                        return file_get_contents('zip://' . $tmpFn . '#doc.kml');
                    } finally {
                        try {
                            unlink($tmpFn);
                        } catch (Exception) {
                            // ignore
                        }
                    }

                default:
                    throw new Exception(
                        '"' . $fnInfo['extension'] . '" is not a KML file extension'
                    );
            }
        })();

        // brokken
        // $file->content = \geoPHP::load($kmlData, 'kml')->out('geojson', true);

        $kmlDoc = KmlParser::fromString($kmlData)->getKml()->getDocument();

        $docName = $kmlDoc->hasName() ? $kmlDoc->getName() : null;
        $docDesc = $kmlDoc->hasDescription() ? $kmlDoc->getDescription() : null;

        $features = array_reduce(
            array_map(
                static function (Folder $folder) use ($docName, $docDesc): array {
                    $folderName = $folder->hasName() ? $folder->getName() : $docName;
                    $folderDesc = $docDesc;

                    return array_map(
                        static function (Placemark $place) use ($folderName, $folderDesc): array {
                            $placeName = $place->hasName() ? $place->getName() : $folderName;
                            $placeDesc = $place->hasDescription() ? $place->getDescription() : $folderDesc;

                            $props = [];
                            if ($placeName) {
                                $props['title'] = $placeName;
                            }
                            if ($placeDesc) {
                                $props['description'] = $placeDesc;
                            }

                            $geom = (static function () use ($place): ?array {
                                $placeElem = $place->getElement();
                                if ($place->hasPoint()) {
                                    $coords = self::parseCoordStr(
                                        $place->getPoint()->getCoordinates()
                                    );
                                    return [
                                        'type' => 'Point',
                                        'coordinates' => $coords,
                                    ];
                                }

                                if ($placeElem->hasChild('LineString')) {
                                    $coords = $placeElem
                                        ->getChild('LineString')
                                        ->getChild('coordinates')
                                        ->getValue();
                                    $coords = explode(' ', trim($coords));
                                    $coords = array_map(
                                        self::parseCoordStr(...),
                                        $coords
                                    );
                                    return [
                                        'type' => 'LineString',
                                        'coordinates' => $coords,
                                    ];
                                }

                                return null;
                            })();

                            return [
                                'type' => 'Feature',
                                'properties' => $props,
                                'geometry' => $geom,
                            ];
                        },
                        $folder->getPlacemarks()
                    );
                },
                $kmlDoc->getFolders()
            ),
            array_merge(...),
            []
        );

        $file->fileName = $fnInfo['dirname'] . '/' . $fnInfo['filename'] . '.geojson';
        $file->content = [
            // the `crs` is against the GeoJson spec, but we need it for comp reasons
            'crs' => [
                'type' => 'name',
                'properties' => [
                    'name' => 'EPSG:4326',
                ],
            ],
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
        $this->pushFile($file);
    }

    private static function parseCoordStr(string $coords): array
    {
        $coords = explode(',', $coords);
        $coords = (static function () use ($coords): array {
            switch (count($coords)) {
                case 2:
                    $coords[] = 0.0;
                    return $coords;

                case 3:
                    return $coords;

                default:
                    throw new Exception('2 or 3 coordinates supported');
            }
        })();
        return array_map(floatval(...), $coords);
    }
}
