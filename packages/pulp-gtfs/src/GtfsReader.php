<?php

declare(strict_types=1);

namespace OpenMapsight\pulpgtfs;

use Generator;

interface GtfsReader
{
    public function csvRows(string $fileName): Generator;
}
