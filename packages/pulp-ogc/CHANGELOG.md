# Changelog

All notable changes to `mapsight/pulp-ogc` are documented here.

## Unreleased

### Added

- Add `WfsClient` to read WFS 2.0.0, 1.1.0 and 1.0.0 GetFeature responses as GeoJSON, with paging, completeness checks against the server's matched count, OGC exception reports and request limits.
- Add `PulpOgc::srcWfs()` to emit a feature type as one Pulp file.
- Add an opt-in check that rejects coordinates rounded below a required number of decimals (`minCoordinateDecimals`, `CoordinatePrecision`).
- Add an opt-in repair for property text that a server encoded as UTF-8 twice (`repairDoubleEncodedUtf8`, `TextRepair`).
- Add `Crs` to compare the EPSG and CRS84 spellings used by OGC services.
