# Pulp GTFS

GTFS helpers for Pulp pipelines, currently focused on generating clean GeoJSON for stops and lines.

## Features

- **Static GTFS input:** Reads standard extracted GTFS `.txt` files from a directory.
- **Stops GeoJSON:** Creates point features from `stops.txt`.
- **Lines GeoJSON:** Creates representative route geometries from `routes.txt`, `trips.txt`, and `shapes.txt`.
- **Bounding box filtering:** Limits output to stops inside a configured `[minLon, minLat, maxLon, maxLat]` bbox.
- **Line enrichment:** Adds served lines and modes to stop features.
- **Accessibility enrichment:** Maps `wheelchair_boarding` into normalized and legacy properties.
- **Presentation-neutral output:** Emits source/data properties only; applications can add icons, HTML descriptions, and other presentation fields afterwards.

## Installation

```bash
composer require mapsight/pulp-gtfs
```

This package depends on `mapsight/pulp`.

## Usage

Supported output types:

- `stops`
- `lines`
- `combined`

Download, cache, and extract GTFS data before running the handler. The handler expects files like `routes.txt`, `stops.txt`, `trips.txt`, `stop_times.txt`, and `shapes.txt` to pass through the pipeline.

```php
use OpenMapsight\Pulp;
use OpenMapsight\PulpGeoJSON;
use OpenMapsight\PulpGTFS;
use OpenMapsight\PulpJSON;

$gtfsDirectory = __DIR__ . '/cache/gtfs-extracted';

Pulp::start()
    ->pipe(Pulp::src('.*\.txt', $gtfsDirectory))
    ->pipe(Pulp::split(
        static fn(Pulp $p): Pulp => $p->pipe(PulpGTFS::geoJson(
            'stops',
            'https://example.com/open-data-docs',
            [10.42, 52.18, 10.65, 52.36],
            null,
            'GTFS',
            'https://example.com/open-data-docs',
            'https://example.com/open-data-docs',
        )),
        static fn(Pulp $p): Pulp => $p->pipe(PulpGTFS::geoJson(
            'lines',
            'https://example.com/open-data-docs',
            [10.42, 52.18, 10.65, 52.36],
            null,
            'GTFS',
            'https://example.com/open-data-docs',
            'https://example.com/open-data-docs',
        )),
    ))
    ->pipe(PulpGeoJSON::calculateBBoxes())
    ->pipe(PulpGeoJSON::addBuildInfo())
    ->pipe(PulpJSON::encodeJSON(JSON_PRETTY_PRINT))
    ->pipe(Pulp::dest(__DIR__ . '/result'))
    ->run();
```

If your extracted files use different names, pass a file map:

```php
PulpGTFS::geoJson('combined', $sourceUrl, $bbox, options: [
    'files' => [
        'routes' => 'my-routes.csv',
        'stops' => 'my-stops.csv',
        'stopTimes' => 'my-stop-times.csv',
        'trips' => 'my-trips.csv',
        'shapes' => 'my-shapes.csv',
    ],
])
```

## Handler Arguments

`PulpGTFS::geoJson(...)` accepts:

- `type`: one of `stops`, `lines`, or `combined`.
- `sourceUrl`: The public or internal source URL used for metadata.
- `bbox`: `[minLon, minLat, maxLon, maxLat]`.
- `departuresBaseUrl`: Optional base URL for live departure data. When set, stop features get `departuresUrl`.
- `sourceName`: Human-readable source name in feature properties and collection metadata.
- `documentationUrl`: Public documentation URL in collection metadata.
- `publicSourceUrl`: Public source URL written to feature properties and collection metadata. Use this when the actual `sourceUrl` contains a private token.

## Stop Properties

Stop features include:

- `gtfsStopId`
- `name`
- `lines`
- `routeIds`
- `modes`
- `modeLabels`
- `wheelchairBoarding`
- `wheelchairBoardingLabel`
- `wheelchairAccessible`
- `stopCode`
- `parentStation`
- `departuresUrl` when configured

## Line Properties

Line features include:

- `gtfsRouteId`
- `gtfsShapeId`
- `name`
- `line`
- `mode`
- `modeLabel`
- `routeType`
- `routeShortName`
- `routeLongName`
- `routeUrl`
- `stroke` when `route_color` is present

## Accessibility Mapping

GTFS `wheelchair_boarding` is mapped as:

- `1`: `wheelchairBoarding = yes`, `wheelchairBoardingLabel = accessible`
- `2`: `wheelchairBoarding = no`, `wheelchairBoardingLabel = not accessible`
- `0` or missing: `wheelchairBoarding = unknown`, `wheelchairBoardingLabel = unknown`

If a stop has no explicit value, the parent station value is used when available.

## Notes

- The line output picks one representative shape per route based on the most common shape among trips serving stops in the bbox.
- The handler streams large GTFS text files and avoids loading complete statewide `trips.txt`, `stop_times.txt`, or `shapes.txt` tables into memory.
- Keep tokenized or licensed GTFS URLs out of generated public GeoJSON by passing a safe `publicSourceUrl`.
- Add app-specific fields such as `mapsightIconId`, `listInformation`, or HTML `description` in the consuming project, not in this generic package.
