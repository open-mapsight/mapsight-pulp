# Changelog

All notable changes to `mapsight/pulp-gtfs` are documented here.

## Unreleased

## 1.0.0 - 2026-06-18

### Added

- Add `PulpGTFS::geoJson()` to generate stops, lines, or combined GeoJSON from GTFS text files in a pipeline.
- Add `GtfsGeoJsonBuilder` for directory-based and reader-based GTFS conversion.
- Add bounding box filtering for stop features and representative line geometry output.
- Add route, mode, source, departure URL, and wheelchair accessibility metadata to generated features.
