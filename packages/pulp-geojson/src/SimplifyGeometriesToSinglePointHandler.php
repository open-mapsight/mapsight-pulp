<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Collection;
use Exception;
use geoPHP;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use Point;

class SimplifyGeometriesToSinglePointHandler extends AbstractHandler
{
    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertGeoJSONFile($file);

        $file->content = Utils::mapFilterFeatures($file->content, static function (array $feature) {
            if (empty($feature['geometry'])) {
                return $feature;
            }

            try {
                $geojson = geoPHP::load(json_encode($feature['geometry']), 'json');
            } catch (Exception) {
                // TODO report exception
                $feature['geometry'] = null;

                return $feature;
            }

            $feature['geometry'] = null;
            if ($geojson instanceof Collection) {
                $components = $geojson->components;
                while (count($components) > 0) {
                    if ($components[0] instanceof Collection) {
                        array_splice($components, 1, 0, $components[0]->components);
                    } elseif ($components[0] instanceof Point) {
                        $feature['geometry'] = $components[0]->out('geojson', true);
                        break;
                    }

                    array_splice($components, 0, 1);
                }
            } elseif ($geojson instanceof Point) {
                $feature['geometry'] = $geojson->out('geojson', true);
            }

            unset($components, $geojson);

            return $feature;
        });

        $this->pushFile($file);
    }
}
