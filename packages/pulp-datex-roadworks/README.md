# Pulp DATEX Roadworks

DATEX II v2 `SituationPublication` helpers for Pulp pipelines: parse, bbox,
GML, and GeoJSON. Fetch a Mobilithek subscription with
`PulpDatexRoadworks::srcMobilithek()` (the same helper as the other DATEX
packages, implemented in `mapsight/pulp-mobilithek`).

## Features

- **Mobilithek src helper:** Configures `Pulp::srcHttp` with the default
  subscription URL, `Accept-Encoding: gzip`, and P12 client-cert curl options.
  Certificate path, password, and subscription ID stay caller-supplied.
- **Situation records → GeoJSON:** One feature per in-bbox `situationRecord`
  (`Roadworks` and other DATEX situation types that carry a location).
- **Locations:** DATEX `<latitude>`/`<longitude>` when present, plus GML
  `posList` / `pos`. EPSG:4326 / WGS84 is lat/lon; CRS84 is lon/lat.
  GeoJSON is always lon, lat.
- **Bounding box filtering:** Limits records to `[minLon, minLat, maxLon, maxLat]`.
- **Feed kind:** Optional `feedKind` (`ald`, `akd`, `bab`, …) is stored on
  each feature so applications can merge multiple catalogs.
- **Presentation-neutral output:** Source and data properties only.
  Applications add icons, HTML descriptions, and localized labels afterwards.

## Installation

```bash
composer require mapsight/pulp-datex-roadworks
```

This package depends on `mapsight/pulp` and `mapsight/pulp-mobilithek`.

## Roadworks GeoJSON

```php
use OpenMapsight\Pulp;
use OpenMapsight\PulpDatexRoadworks;
use OpenMapsight\PulpJSON;

Pulp::start()
    ->pipe(PulpDatexRoadworks::srcMobilithek(
        $subscriptionId,
        $certPath,
        $certPassword,
        $ifModifiedSince,
        'mobilithek.xml',
    ))
    ->pipe(PulpDatexRoadworks::roadworksGeoJson(
        [10.30, 52.12, 10.80, 52.42],
        'https://example.com/open-data-docs',
        'DATEX Roadworks',
        'https://example.com/open-data-docs',
        'https://example.com/open-data-docs',
        ['feedKind' => 'ald'],
    ))
    ->pipe(PulpJSON::encodeJSON(JSON_PRETTY_PRINT))
    ->pipe(Pulp::dest(__DIR__ . '/result'))
    ->run();
```

The handler accepts a DATEX II v2 XML string, a SOAP container, or an
already-decoded array. Records without a usable point or GML line are dropped.

## Builder

```php
use OpenMapsight\PulpDatexRoadworks;

$builder = PulpDatexRoadworks::roadworksBuilder(
    [10.30, 52.12, 10.80, 52.42],
    'https://example.com/open-data-docs',
    'DATEX Roadworks',
    'https://example.com/open-data-docs',
    'https://example.com/open-data-docs',
    ['feedKind' => 'akd'],
);

$geoJson = $builder->build($xml);
$features = $builder->featuresFromPublication($xml);
```

## Feature properties

Roadworks features include:

- `recordId`, `situationId`
- `name`, `comment`, `locationName`
- `recordType` (DATEX `xsi:type`, often `Roadworks`)
- `feedKind`
- `roadMaintenanceType`, `roadworksDuration`, `underTraffic`
- `probabilityOfOccurrence`
- `start`, `end`, `updatedAt`
- `source`, `sourceUrl`, `documentationUrl`

Geometry is a `Point`, a `LineString` from GML, or a `GeometryCollection`
of both when a display point and a line are present.

## Notes

- Certificate path, password, and subscription ID stay caller-supplied.
- Presentation (icons, localized copy, catalog merge) belongs in the
  consuming application.
- Alert-C location-table lookup is not implemented. GML `posList` is
  enough for Autobahn MIA publications.
- `srcMobilithek()` only configures `Pulp::srcHttp`. Cache with `PulpCache::remember`.
