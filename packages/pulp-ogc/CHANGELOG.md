# Changelog

All notable changes to `mapsight/pulp-ogc` are documented here.

## Unreleased

## 1.0.0 - 2026-11-20

### Added

- Add `WfsClient` to read WFS 2.0.0, 1.1.0 and 1.0.0 GetFeature responses as GeoJSON, with paging, completeness checks against the server's matched count, OGC exception reports and request limits.
- Add `PulpOgc::srcWfs()` to emit a feature type as one Pulp file.
- Add `Crs` to compare the EPSG and CRS84 spellings used by OGC services.
