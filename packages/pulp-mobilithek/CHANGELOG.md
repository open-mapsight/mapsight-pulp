# Changelog

All notable changes to `mapsight/pulp-mobilithek` are documented here.

## Unreleased

## 1.1.0 - 2026-10-30

### Added

- Add `PulpMobilithek::drainMobilithek()` for bounded, ordered delta-buffer draining,
  baseline bootstrap, and an optional ETag-deduplicated head probe that does not skip the cursor.
- Document the remaining same-second pull limitation and durable push reception.

## 1.0.0 - 2026-09-04

### Added

- Add `PulpMobilithek::srcMobilithek()` to configure `Pulp::srcHttp` with the default Mobilithek URL, gzip, and P12 client-cert curl options.
- Add `PulpMobilithek::mobilithekGuzzleOptions()` for the same defaults outside a pipeline.
