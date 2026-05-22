<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use geoPHP;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class FlattenGeometryHandler extends AbstractHandler
{
    /**
     * @param pulp\File $file
     *
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
                $geometry = geoPHP::load(json_encode($feature['geometry']), 'json');
                $feature['geometry'] = geoPHP::geometryReduce($geometry)->out('geojson');

                return $feature;
            } catch (Exception) {
                // TODO report exception
                return $feature;
            }
        });

        $this->pushFile($file);
    }
}
