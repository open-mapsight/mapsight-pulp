<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulpzip\UnzipHandler;
use OpenMapsight\pulpzip\ZipHandler;

class PulpZip
{
    public static function unzip(string|array $patterns = '.*', array $options = []): UnzipHandler
    {
        return new UnzipHandler($patterns, $options);
    }

    public static function zip(string $fileName, array $options = []): ZipHandler
    {
        return new ZipHandler($fileName, $options);
    }
}
