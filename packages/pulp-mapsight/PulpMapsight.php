<?php

declare(strict_types=1);

namespace OpenMapsight;

use OpenMapsight\pulpmapsight\FeatureSourcesHandler;

class PulpMapsight
{
    /**
     * Reads the feature sources of Mapsight pages: replaces the incoming
     * HTML files with one file per source that loads over HTTP (`xhr-json`),
     * found in the dehydrated state of the page's embeds. Each file is named
     * by the source's path (with query) and holds
     * `['url' => …, 'path' => …, 'featureSourceIds' => […], 'titles' => […]]`,
     * where `titles` are the titles of the map layers showing the source.
     * Sources found on several pages are emitted once. Pages without
     * sources add nothing; without any, nothing is emitted.
     *
     * Relative URLs resolve against `$origin`; sources on other hosts are
     * skipped.
     *
     * ## Options
     * * `attribute`: the data attribute holding the state (default: `"data-dehydrated-state"`)
     * * `types`: source type prefixes to read (default: `["xhr-json"]`)
     *
     * @param array{attribute?: string, types?: list<string>} $options
     */
    public static function featureSources(string $origin, array $options = []): FeatureSourcesHandler
    {
        return new FeatureSourcesHandler($origin, $options);
    }
}
