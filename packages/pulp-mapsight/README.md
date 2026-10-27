# Pulp Mapsight

Mapsight helpers for Pulp pipelines.

## Features

- **Feature sources of Mapsight pages:** Read which GeoJSON sources a published Mapsight map uses, and the titles of the layers that show them, from the dehydrated state of its embeds. Jobs that work on "everything this map shows" follow the map's configuration instead of a list kept by hand.

## Installation

```bash
composer require mapsight/pulp-mapsight
```

## Read Feature Sources

`featureSources()` replaces the incoming HTML pages with one file per source that loads over HTTP (`xhr-json`). Each file is named by the source's path with query and holds:

```php
[
    'url' => 'https://www.example.org/geojson/museums.geojson',
    'path' => '/geojson/museums.geojson',
    'featureSourceIds' => ['museums'],
    'titles' => ['Museums', 'Culture'],
]
```

```php
use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpCache;
use OpenMapsight\PulpJSON;
use OpenMapsight\PulpMapsight;

Pulp::start()
    ->pipe(Pulp::srcHttp('GET', 'https://www.example.org/map/', ['timeout' => 30], 'map.html'))
    ->pipe(PulpMapsight::featureSources('https://www.example.org'))
    ->pipe(Pulp::each(static fn(File $source): Pulp => Pulp::start()
        ->pipe(Pulp::srcHttp('GET', $source->content['url'], ['timeout' => 30], $source->fileName))))
    ->pipe(PulpJSON::decodeJSON())
    // every GeoJSON source of the map …
    ->run();
```

- Relative source URLs resolve against the origin. Sources on other hosts are skipped.
- A source found on several pages, or in several embeds, is emitted once with all its ids and titles.
- Pages without sources add nothing. Without any source, nothing is emitted: wrap the discovery in `PulpCache::remember()` with `keepCacheWhenEmpty` to keep the last list when a page is broken.
- The content is a plain array, so the files can be cached.

Options:

- `attribute`: the data attribute holding the state (default `data-dehydrated-state`).
- `types`: source type prefixes to read (default `['xhr-json']`).
