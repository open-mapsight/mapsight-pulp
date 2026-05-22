<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use Geometry;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use Point;

class CalculateCentroidsHandler extends AbstractHandler
{
    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertGeoJSONFile($file);

        $file->content = Utils::mapFilterFeatures($file->content, static function (array $feature): array {
            if (!isset($feature['properties'])) {
                $feature['properties'] = [];
            }

            $feature['properties']['centroid'] = null;

            $geom = Utils::getGeometryForFeature($feature);
            if (!$geom instanceof Geometry) {
                return $feature;
            }

            /** @var Point $centroid */
            $centroid = $geom->getCentroid();
            $feature['properties']['centroid'] = [
                'x' => $centroid->getX(),
                'y' => $centroid->getY(),
            ];

            return $feature;
        });

        $this->pushFile($file);
    }
}
