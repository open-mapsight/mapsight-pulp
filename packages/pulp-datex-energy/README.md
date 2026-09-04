# Pulp DATEX Energy

AFIR DATEX II v3 energy-infrastructure helpers for Pulp pipelines. Two layers:
Mobilithek source defaults, then presentation-neutral JSON → GeoJSON.

## Features

- **Mobilithek src helper:** Configures `Pulp::srcHttp` with the default
  subscription URL, `Accept-Encoding: gzip`, and P12 client-cert curl options.
  Certificate path, password, and subscription ID stay caller-supplied.
- **Static table → GeoJSON:** One point feature per `energyInfrastructureSite`
  from `aegiEnergyInfrastructureTablePublication`.
- **Status records:** EVSE records from `aegiEnergyInfrastructureStatusPublication`
  (`available`, `charging`, `occupied`, `reserved`, `outOfOrder`, `inoperative`, …).
- **Bounding box filtering:** Limits sites to `[minLon, minLat, maxLon, maxLat]`.
- **Presentation-neutral output:** Source and data properties only. Applications
  add icons, HTML descriptions, and localized labels afterwards.

## Installation

```bash
composer require mapsight/pulp-datex-energy
```

This package depends on `mapsight/pulp` and `mapsight/pulp-mobilithek`.

## Fetch a subscription

```php
use OpenMapsight\Pulp;
use OpenMapsight\PulpCache;
use OpenMapsight\PulpDatexEnergy;
use OpenMapsight\PulpJSON;

$source = Pulp::start()
    ->pipe(PulpDatexEnergy::srcMobilithek(
        $subscriptionId,
        $certPath,
        $certPassword,
        $ifModifiedSince,
        'mobilithek.json',
        ['timeout' => 180, 'http_errors' => false],
        ['sink' => true, 'successStatuses' => [200, 304]],
    ));

$files = Pulp::start()
    ->pipe(PulpCache::remember($source, __DIR__ . '/cache', [
        'key' => 'datex-energy-static',
        'ttl' => 86400,
        'fallbackToStale' => true,
    ]))
    ->pipe(PulpJSON::decodeJSON())
    ->pipe(PulpDatexEnergy::sitesGeoJson(
        [10.42, 52.18, 10.65, 52.36],
        'https://example.com/open-data-docs',
        'DATEX Energy',
        'https://example.com/open-data-docs',
        'https://example.com/open-data-docs',
    ))
    ->run();
```

Pass `sink => true` on `srcMobilithek()` (a core `Pulp::srcHttp` option) so the
~103 MB nationwide static table stays a path-backed file instead of a PHP string.

## Sites GeoJSON

```php
use OpenMapsight\Pulp;
use OpenMapsight\PulpDatexEnergy;
use OpenMapsight\PulpJSON;

Pulp::start()
    ->pipe(Pulp::src('table.json', __DIR__ . '/input'))
    ->pipe(PulpJSON::decodeJSON())
    ->pipe(PulpDatexEnergy::sitesGeoJson(
        [10.42, 52.18, 10.65, 52.36],
        'https://example.com/open-data-docs',
    ))
    ->pipe(PulpJSON::encodeJSON(JSON_PRETTY_PRINT))
    ->pipe(Pulp::dest(__DIR__ . '/result'))
    ->run();
```

The handler also accepts a raw JSON string. Prefer a `preferredLang` option when
DATEX `values` include more than one language; otherwise the first non-empty
value is used.

```php
PulpDatexEnergy::sitesGeoJson($bbox, $sourceUrl, options: [
    'preferredLang' => 'en',
])
```

## Status records

```php
$points = PulpDatexEnergy::extractPointStatuses($publication);
$cache = PulpDatexEnergy::applyPacketToCache($cache, $points, $packetType, $lastModified);
$merged = PulpDatexEnergy::mergeStatusIntoFeatureCollection($featureCollection, $cache['points']);
```

Or in a pipeline:

```php
Pulp::start()
    ->pipe(Pulp::src('status.json', __DIR__ . '/input'))
    ->pipe(PulpDatexEnergy::statusRecords())
    ->run();
```

A `SNAPSHOT` packet replaces the EVSE cache. A `DELTA` packet upserts by EVSE ID.

To keep an on-disk accumulator across runs (and to ignore 304/204 packets):

```php
Pulp::start()
    ->pipe(Pulp::src('status.json', __DIR__ . '/input'))
    ->pipe(PulpDatexEnergy::accumulateStatus(__DIR__ . '/cache/charging-status.json'))
    ->run();
```

## Site properties

Site features include:

- `siteId`
- `name`
- `operator`
- `addressLine`, `postcode`, `city`, `countryCode`
- `evseIds`
- `chargingPoints` (`evseId`, `stationId`, `watts`, `currentType`, `connectorTypes`)
- `connectorTypes` (DATEX codes such as `iec62196T2`, `iec62196T2COMBO`)
- `currentTypes` (`ac`, `dc`)
- `maxPowerWatts`
- `lastUpdated`
- `source`, `sourceUrl`

After a status merge, matched features also include:

- `chargingAvailability`: `available`, `partial`, `occupied`, `inoperative`, or `unknown`
- `chargingAvailablePoints`
- `chargingObservedPoints`
- `chargingStatuses`
- `chargingUpdatedAt`
- `evseStatuses`

## Notes

- Certificate path, password, and subscription ID stay caller-supplied.
- Presentation (icons, localized copy, spatial joins) belongs in the
  consuming application.
- `srcMobilithek()` only configures `Pulp::srcHttp`. Cache with `PulpCache::remember`.
  The same helper is also available as `PulpMobilithek::srcMobilithek()`.
