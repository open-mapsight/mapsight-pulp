<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgeojson;

use Exception;
use OpenMapsight\GeoJsonReproject;
use OpenMapsight\pulp;
use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class ReprojectHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return [
            'destProjection',
            [
                'key' => 'options',
                'default' => [
                    'includeZCoordinate' => true,
                    'useIntegerCoordinates' => false,
                ],
                'handler' => 'merge',
            ],
        ];
    }

    /**
     * @param pulp\File $file
     * @throws Exception
     */
    public function onFile(File $file): void
    {
        Utils::assertGeoJSONFile($file);

        $file->content = GeoJsonReproject::reproject(
            $file->content,
            $this->cp->destProjection,
            $this->cp->options
        );

        $this->pushFile($file);
    }
}
