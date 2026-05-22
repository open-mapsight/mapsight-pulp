<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use Geometry;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class CalculateBBoxesHandler extends AbstractHandler
{
    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertGeoJSONFile($file);

        $file->content = Utils::mapFilterFeatures($file->content, static function (array $feature): array {
            $feature['bbox'] = null;

            $geom = Utils::getGeometryForFeature($feature);
            if (!$geom instanceof Geometry) {
                return $feature;
            }

            $sBbox = $geom->getBBox();

            $dims = array_filter(['x', 'y', 'z'], static fn(string $dim): bool => isset($sBbox['max' . $dim], $sBbox['min' . $dim]));

            $dBbox = [];
            foreach ($dims as $dim) {
                $dBbox[] = $sBbox['min' . $dim];
            }

            foreach ($dims as $dim) {
                $dBbox[] = $sBbox['max' . $dim];
            }

            $feature['bbox'] = $dBbox;

            return $feature;
        });

        $this->pushFile($file);
    }
}
