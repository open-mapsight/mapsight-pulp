<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulpogc\SrcWfsHandler;
use OpenMapsight\pulpogc\WfsClient;

class PulpOgc
{
    /**
     * Emit one decoded GeoJSON FeatureCollection after every page of the feature type has been read.
     *
     * @param array<string, mixed> $request See WfsClient::getFeature().
     */
    public static function srcWfs(
        WfsClient $client,
        string $typeName,
        string $fileName,
        array $request = [],
    ): SrcWfsHandler {
        return new SrcWfsHandler($client, $typeName, $fileName, $request);
    }
}
