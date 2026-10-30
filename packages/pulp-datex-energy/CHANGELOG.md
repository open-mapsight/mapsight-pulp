# Changelog

All notable changes to `mapsight/pulp-datex-energy` are documented here.

## Unreleased

## 1.1.2 - 2026-10-30

### Fixed

- Apply each status packet in order, including snapshots in the middle of a run.
- Detect snapshots from DATEX `codedExchangeProtocol`, even without an HTTP Type header.
- Reject older point timestamps and malformed/non-success packets.
- Commit the cache atomically after a successful run, preserving file permissions.
- Honor `mobilithekAdvanceCursor = false` on head probes; expose `accumulatorVersion = 2`
  so consumers can bootstrap legacy caches that missed snapshot resets.

## 1.1.1 - 2026-10-29

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
