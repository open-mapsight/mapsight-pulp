<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexenergy;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use RuntimeException;

class MergeStatusHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['statusByEvse'];
    }

    public function onFile(File $file): void
    {
        $collection = $file->content;
        if (is_string($collection)) {
            $collection = GeoJsonHandler::publicationFromFile($file);
        }
        if (!is_array($collection)) {
            throw new RuntimeException('DATEX Energy status merge expects a FeatureCollection');
        }

        $merged = DatexEnergyStatus::mergeStatusIntoFeatureCollection(
            $collection,
            is_array($this->cp->statusByEvse) ? $this->cp->statusByEvse : []
        );
        $file->content = $merged['featureCollection'];
        $this->pushFile($file);
    }
}
