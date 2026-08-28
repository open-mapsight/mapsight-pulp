# Pulp DATEX Fuel

MTS-K DATEX II v2 petrol-station helpers for Pulp pipelines. Two layers:
Mobilithek source defaults, then presentation-neutral XML → GeoJSON.

This is the parse / bbox / GeoJSON layer only. It is not a city job and it is
not AFIR charging (`mapsight/pulp-datex-energy`).

## Features

- **Mobilithek src helper:** Configures `Pulp::srcHttp` with the default
  subscription URL, `Accept-Encoding: gzip`, and P12 client-cert curl options.
  Certificate path, password, and subscription ID stay caller-supplied.
- **Grunddaten → GeoJSON:** One point feature per `PetrolStation` from a
  `PetrolStationPublication`.
- **Preisdaten:** `FuelPrice` records for Super E5, Super E10, and Diesel,
  joined to stations on the station GUID.
- **Bounding box filtering:** Limits stations to `[minLon, minLat, maxLon, maxLat]`.
- **Presentation-neutral output:** Source and data properties only. Applications
  add icons, HTML descriptions, and localized labels afterwards.

## Access

Consumer pull of the aggregated MTS-K collector feeds requires
Verbraucher-Informationsdienst admission under § 6 MTSKraftV (nationwide,
permanent, unrestricted) or a VID third-party regional feed (the VID stays
liable). This package only parses publications you already have a right to
fetch. It does not apply for VID status and does not check legal eligibility
at runtime.

- [§ 5 MTSKraftV](https://www.gesetze-im-internet.de/mtskraftv/__5.html)
- [§ 6 MTSKraftV](https://www.gesetze-im-internet.de/mtskraftv/__6.html)
- [Hinweise der MTS-K zur Nutzung der Mobilithek (Stand April 2026)](https://www.bundeskartellamt.de/SharedDocs/Publikation/DE/Sonstiges/MTS-K/MTS_Hinweis_Nutzung_Mobilithek.pdf?__blob=publicationFile&v=3)
- [Info VID Verbraucher (26 Feb 2025)](https://www.bundeskartellamt.de/SharedDocs/Publikation/DE/Sonstiges/MTS-K/Info_VID_Verbraucher.pdf?__blob=publicationFile&v=4)
- [Schema (DATEX II v2, v01-01)](https://mobilithek.info/cms/downloads/datenmodell-mts-k-01-01)
- [WSDL](https://mobilithek.info/cms/downloads/wsdl-mtsk)

## Installation

```bash
composer require mapsight/pulp-datex-fuel
```

This package depends on `mapsight/pulp`.

## Fetch a subscription

```php
use OpenMapsight\Pulp;
use OpenMapsight\PulpCache;
use OpenMapsight\PulpDatexFuel;
use OpenMapsight\PulpJSON;

$source = Pulp::start()
    ->pipe(PulpDatexFuel::srcMobilithek(
        $subscriptionId,
        $certPath,
        $certPassword,
        $ifModifiedSince,
    ));

$files = Pulp::start()
    ->pipe(PulpCache::remember($source, __DIR__ . '/cache', [
        'key' => 'datex-fuel-stations',
        'ttl' => 86400,
        'fallbackToStale' => true,
    ]))
    ->pipe(PulpDatexFuel::stationsGeoJson(
        [6.05, 50.76, 6.08, 50.82],
        'https://example.com/open-data-docs',
        'DATEX Fuel',
        'https://example.com/open-data-docs',
        'https://example.com/open-data-docs',
    ))
    ->run();
```

`Pulp::srcHttp` still loads the full response body as a string. A nationwide
dump may need a disk sink in core pulp later. That belongs in core pulp, not
this package.

## Stations GeoJSON

```php
use OpenMapsight\Pulp;
use OpenMapsight\PulpDatexFuel;
use OpenMapsight\PulpJSON;

Pulp::start()
    ->pipe(Pulp::src('stations.xml', __DIR__ . '/input'))
    ->pipe(PulpDatexFuel::stationsGeoJson(
        [6.05, 50.76, 6.08, 50.82],
        'https://example.com/open-data-docs',
    ))
    ->pipe(PulpJSON::encodeJSON(JSON_PRETTY_PRINT))
    ->pipe(Pulp::dest(__DIR__ . '/result'))
    ->run();
```

The handler accepts a DATEX II v2 XML string, a Mobilithek SOAP container, or
an already-decoded array. Stations without coordinates are dropped.

## Price merge

```php
$prices = PulpDatexFuel::extractPrices($publication);
$cache = PulpDatexFuel::applyPricePacketToCache($cache, $prices, $packetType, $lastModified);
$merged = PulpDatexFuel::mergePricesIntoFeatureCollection($featureCollection, $cache['prices']);
```

Or in a pipeline:

```php
Pulp::start()
    ->pipe(Pulp::src('prices.xml', __DIR__ . '/input'))
    ->pipe(PulpDatexFuel::priceRecords())
    ->run();
```

A `SNAPSHOT` packet replaces the station-price cache. A `DELTA` packet upserts
by station GUID. Published MTS-K examples are snapshots; the DELTA path is the
hook if a collector later sends increments.

## Station properties

Station features include:

- `stationId` (GUID)
- `name`
- `brand`, `operator` (empty string when the publication omits them)
- `addressLine`, `postcode`, `city`, `countryCode`
- `openingHours` (DATEX-derived list of `{days, periods}` blocks; `days` use
  DATEX `DayEnum` values such as `monday` and `publicHoliday`)
- `stationUpdatedAt`
- `source`, `sourceUrl`

After a price merge, matched features also include:

- `e5`, `e10`, `diesel` (float with up to three decimals, or `null` when that product is absent)
- `pricesUpdatedAt` (`dateOfPrice` / Änderungszeitpunkt)

## Notes

- Do not put a `.p12` or city-specific subscription IDs in this package.
- Keep German copy, Mapsight icons, “cheapest nearby” text, and VID application
  flow in the consuming city job.
- `srcMobilithek()` only configures `Pulp::srcHttp`. Cache with `PulpCache::remember`.
