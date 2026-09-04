# Changelog

All notable changes to `mapsight/pulp-datex-roadworks` are documented here.

## Unreleased

## 1.0.0 - 2026-09-04

### Added

- Add `PulpDatexRoadworks::roadworksGeoJson()` and `DatexRoadworksBuilder` to emit one GeoJSON feature per in-bbox DATEX II `SituationPublication` record.
- Add bounding box filtering and presentation-neutral roadworks properties (record id, validity, maintenance type, duration).
- Read DATEX `<latitude>`/`<longitude>` and GML `posList` / `pos` (EPSG:4326 lat/lon → GeoJSON lon, lat).
- Add `PulpDatexRoadworks::srcMobilithek()` and `mobilithekGuzzleOptions()`, delegated to `mapsight/pulp-mobilithek`.
