<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulpcache\CacheHandler;
use OpenMapsight\pulpcache\RememberHandler;

class PulpCache
{
    public static function cache(
        string $cacheDirectory,
        array $options = []
    ): CacheHandler {
        return new CacheHandler($cacheDirectory, $options);
    }

    public static function remember(
        Pulp $source,
        string $cacheDirectory,
        array $options = []
    ): RememberHandler {
        return new RememberHandler($source, $cacheDirectory, $options);
    }
}
