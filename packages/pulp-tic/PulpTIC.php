<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulptic\ParseHandler;
use OpenMapsight\pulptic\ToGeoJSONHandler;

class PulpTIC
{
    public static function parse(): ParseHandler
    {
        return new ParseHandler();
    }

    public static function toGeoJSON(): ToGeoJSONHandler
    {
        return new ToGeoJSONHandler();
    }
}
