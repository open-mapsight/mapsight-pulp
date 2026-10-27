# Pulp Search Index

Search index of GeoJSON features for Pulp pipelines: build it from many sources, store it in a database, and search it as you type.

## Features

- **Small rows:** One row per feature: id, title, subtitle, normalized words, place numbers, first coordinate. No geometry, no properties.
- **German-friendly matching:** Case, accents and punctuation do not count; umlauts, their ASCII spellings and ß/ss compare equally. Markup is stripped, script and style content never matches.
- **Ranking:** Source priority, exact place number, exact name, name prefix, all words in the name, then German alphabetical order with numbers by value. The same place in two sources appears once.
- **Layer titles:** A query word found in a title of the source (for example "stops") matches every place of that source.
- **Database:** Two tables in MySQL/MariaDB or SQLite, written in one transaction. Unchanged sources are not rewritten and keep their `updated_at`.

## Installation

```bash
composer require mapsight/pulp-search-index
```

Requires `ext-intl` (German collation) and `mapsight/pulp-pdo`.

## Build and Store an Index

Each incoming file is one source, keyed by its file name. Its content may be a JSON string or a decoded FeatureCollection; its `titles` property (or the `titles` option) names the source.

The list of sources may come from anywhere, for example a configuration, or a CMS export of the map:

```php
use OpenMapsight\Pulp;
use OpenMapsight\pulp\File;
use OpenMapsight\PulpCache;
use OpenMapsight\PulpSearchIndex;

// source path => titles of the layers that show it
$sources = [
    '/geojson/museums.geojson' => ['Museums'],
    '/geojson/stops.geojson' => ['Stops', 'Public transport'],
];
$requests = [];
foreach ($sources as $path => $titles) {
    $requests[] = $request = new File($path);
    $request->content = $titles;
}

Pulp::start()
    ->pipe(Pulp::src($requests))
    ->pipe(Pulp::each(
        static fn(File $source): Pulp => Pulp::start()
            // a source that fails keeps its last good copy
            ->pipe(PulpCache::remember(
                Pulp::start()->pipe(Pulp::srcHttp('GET', 'https://www.example.org' . $source->fileName, ['timeout' => 30], $source->fileName)),
                __DIR__ . '/cache',
                ['key' => 'search-' . sha1($source->fileName), 'ttl' => 0, 'fallbackToStale' => true],
            ))
            ->pipe(Pulp::map(static function (File $file) use ($source): File {
                $file->titles = $source->content;

                return $file;
            })),
        ['skipExceptions' => true],
    ))
    ->pipe(PulpSearchIndex::index(options: ['skipExceptions' => true]))
    ->pipe(PulpSearchIndex::toDatabase(static fn(): PDO => new PDO($dsn, $user, $password)))
    ->run();
```

`index()` emits one file:

```php
[
    'version' => 1,
    'generatedAt' => 1793117400000,
    'sources' => [
        '/geojson/museums.geojson' => [
            'titles' => ['Museums'],
            'rows' => [['12', 'City Museum', 'Culture', 'city museum 12 culture', [], [10.52, 52.26]]],
        ],
    ],
]
```

Without any source, it emits nothing, so a broken run never empties the database. Encode it with `PulpJSON::encodeJSON()` to keep a file instead of, or in addition to, the database.

`toDatabase()` creates `search_index_sources` and `search_index_rows` if needed (option `prefix` changes the names) and replaces the stored index in one transaction.

## Search

```php
use OpenMapsight\pulpsearchindex\SearchIndexDatabase;

$result = SearchIndexDatabase::search($pdo, [[0, '/geojson/museums.geojson'], [1, '/geojson/parks.geojson']], 'museum');
// ['places' => [[source, id, title, subtitle, point, score], …], 'missing' => [requested sources not in the index]]
```

- `$requested` lists sources as `[priority, key]`, lower priority first; `null` searches every source with equal priority.
- At most 40 places by default (`$limit`).
- `SearchIndexDatabase::titles($pdo, $keys)` returns the titles of stored sources.
- `SearchIndex::search()` ranks an index held in memory the same way.

## Same Results Elsewhere

Clients that build rows or rank places themselves, for example a browser searching the data already on its map, must follow the same rules. `test/fixtures` holds GeoJSON input, the expected rows, and expected rankings for a set of queries; check your implementation against them.
