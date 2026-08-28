<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexfuel;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;
use RuntimeException;

class MergePricesHandler extends AbstractHandler
{
    protected function getConstructorParamDefs(): array
    {
        return ['pricesByStationId'];
    }

    public function onFile(File $file): void
    {
        $collection = $file->content;
        if (is_string($collection)) {
            $collection = GeoJsonHandler::publicationFromFile($file);
        }
        if (!is_array($collection)) {
            throw new RuntimeException('DATEX Fuel price merge expects a FeatureCollection');
        }

        $merged = DatexFuelPrices::mergePricesIntoFeatureCollection(
            $collection,
            is_array($this->cp->pricesByStationId) ? $this->cp->pricesByStationId : []
        );
        $file->content = $merged['featureCollection'];
        $this->pushFile($file);
    }
}
