<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexfuel;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class PricesHandler extends AbstractHandler
{
    /** @var list<array{stationId: string, e5: float|null, e10: float|null, diesel: float|null, updatedAt: string}> */
    private array $prices = [];

    public function onFile(File $file): void
    {
        $this->prices = array_merge(
            $this->prices,
            DatexFuelPrices::extractPrices(GeoJsonHandler::publicationFromFile($file))
        );
    }

    public function onEnd(): void
    {
        $file = new File('datex-fuel-prices.json');
        $file->content = $this->prices;
        $this->pushFile($file);
    }
}
