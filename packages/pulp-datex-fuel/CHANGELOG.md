# Changelog

All notable changes to `mapsight/pulp-datex-fuel` are documented here.

## Unreleased

## 1.1.0 - 2026-09-02

### Added

- Add `PulpDatexFuel::accumulatePrices()` to apply SNAPSHOT/DELTA packets to an on-disk station-price cache, leaving the cache unchanged on HTTP 304/204.

## 1.0.0 - 2026-08-28

### Added

- Add `PulpDatexFuel::srcMobilithek()` to configure `Pulp::srcHttp` with the default Mobilithek URL, gzip, and P12 client-cert curl options.
- Add `PulpDatexFuel::stationsGeoJson()` and `DatexFuelStationsBuilder` to emit one GeoJSON feature per MTS-K DATEX II petrol station.
- Add bounding box filtering and presentation-neutral station properties (GUID, brand, address, DATEX opening-hours blocks).
- Add price extraction, SNAPSHOT/DELTA cache updates, and presentation-neutral E5/E10/Diesel merge on station GUID.
