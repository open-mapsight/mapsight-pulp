# Changelog

All notable changes to `mapsight/pulp-datex-energy` are documented here.

## Unreleased

### Changed

- Delegate `srcMobilithek()` and `mobilithekGuzzleOptions()` to `mapsight/pulp-mobilithek`.

## 1.1.0 - 2026-09-02

### Added

- Add `PulpDatexEnergy::accumulateStatus()` to apply SNAPSHOT/DELTA packets to an on-disk EVSE cache, leaving the cache unchanged on HTTP 304/204.

### Changed

- Document `srcMobilithek(..., options: ['sink' => true])` now that core pulp can disk-sink large responses.

## 1.0.0 - 2026-08-28

### Added

- Add `PulpDatexEnergy::srcMobilithek()` to configure `Pulp::srcHttp` with the default Mobilithek URL, gzip, and P12 client-cert curl options.
- Add `PulpDatexEnergy::sitesGeoJson()` and `DatexEnergySitesBuilder` to emit one GeoJSON feature per AFIR DATEX II energy infrastructure site.
- Add bounding box filtering and presentation-neutral site properties (DATEX connector codes, watts, EVSE IDs).
- Add status extraction, SNAPSHOT/DELTA cache updates, and presentation-neutral EVSE status merge.
