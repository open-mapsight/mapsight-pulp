<?php

declare(strict_types=1);

namespace OpenMapsight\pulpdatexenergy;

use OpenMapsight\pulp\AbstractHandler;
use OpenMapsight\pulp\File;

class StatusHandler extends AbstractHandler
{
    /** @var list<array{evseId: string, status: string, lastUpdated: string, siteId: string, stationId: string}> */
    private array $points = [];

    public function onFile(File $file): void
    {
        $this->points = array_merge(
            $this->points,
            DatexEnergyStatus::extractPointStatuses(GeoJsonHandler::publicationFromFile($file))
        );
    }

    public function onEnd(): void
    {
        $file = new File('datex-energy-status.json');
        $file->content = $this->points;
        $this->pushFile($file);
    }
}
