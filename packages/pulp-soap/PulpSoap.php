<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulpsoap\SrcSoapHandler;

class PulpSoap
{
    public static function srcSoap($endPoint, $location, $url): SrcSoapHandler
    {
        return new SrcSoapHandler($endPoint, $location, $url);
    }
}
